<?php

namespace Tests\Feature\Batches;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Pond;
use App\Models\Producer;
use App\Models\Species;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BatchAccessTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private Farm $assignedFarm;

    private Batch $assignedBatch;

    private Batch $siblingBatch;

    private Batch $foreignBatch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->assignedFarm = Farm::factory()->for($this->producer)->create();
        $siblingFarm = Farm::factory()->for($this->producer)->create();

        $this->assignedBatch = Batch::factory()->for(Pond::factory()->for($this->assignedFarm))->create(['code' => 'ASIG-1']);
        $this->siblingBatch = Batch::factory()->for(Pond::factory()->for($siblingFarm))->create(['code' => 'HERM-1']);
        $this->foreignBatch = Batch::factory()->create(['code' => 'AJENO-1']);
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'productor' => [Role::Producer],
            'técnico' => [Role::Technician],
            'consulta' => [Role::Viewer],
        ];
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function assignableRoles(): array
    {
        return [
            'técnico' => [Role::Technician],
            'consulta' => [Role::Viewer],
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function adminRoutes(Batch $batch): array
    {
        return [
            ['get', route('batches.create')],
            ['post', route('batches.store')],
            ['get', route('batches.edit', $batch)],
            ['put', route('batches.update', $batch)],
            ['get', route('batches.close.confirm', $batch)],
            ['patch', route('batches.close', $batch)],
            ['get', route('batches.cancel.confirm', $batch)],
            ['patch', route('batches.cancel', $batch)],
        ];
    }

    private function userFor(Role $role): User
    {
        $user = User::factory()->role($role)->forProducer($this->producer)->create();

        if ($role !== Role::Producer) {
            $this->assignedFarm->users()->attach($user);
        }

        return $user;
    }

    public function test_guests_cannot_access_batches(): void
    {
        $routes = [
            ['get', route('batches.index')],
            ['get', route('batches.show', $this->assignedBatch)],
            ...$this->adminRoutes($this->assignedBatch),
        ];

        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_run_administrative_actions(Role $role): void
    {
        $user = $this->userFor($role);

        foreach ($this->adminRoutes($this->assignedBatch) as [$method, $url]) {
            $this->actingAs($user)->{$method}($url, [
                'pond_id' => $this->assignedBatch->pond_id, 'species_id' => Species::factory()->create()->id,
                'code' => 'HACK', 'name' => 'Cambio', 'stocked_at' => today()->toDateString(),
                'initial_quantity' => '10', 'initial_average_weight_g' => '1', 'closed_at' => today()->toDateString(),
            ])->assertForbidden();
        }

        $this->assertDatabaseMissing('batches', ['code' => 'HACK']);
        $this->assertTrue($this->assignedBatch->fresh()->isActive());
        $this->assertNull($this->assignedBatch->fresh()->closed_at);
    }

    public function test_producer_sees_batches_of_all_their_farms_only(): void
    {
        $this->actingAs($this->userFor(Role::Producer))
            ->get(route('batches.index'))
            ->assertOk()
            ->assertSee('ASIG-1')
            ->assertSee('HERM-1')
            ->assertDontSee('AJENO-1')
            ->assertDontSee('Nuevo lote')
            ->assertDontSee('Lotes activos');

        $this->get(route('batches.show', $this->siblingBatch))
            ->assertOk()
            ->assertDontSee(route('batches.edit', $this->siblingBatch))
            ->assertDontSee(route('batches.close.confirm', $this->siblingBatch));
        $this->get(route('batches.show', $this->foreignBatch))->assertForbidden();
        $this->get(route('batches.index', ['farm' => $this->foreignBatch->pond->farm_id]))->assertDontSee('AJENO-1');
    }

    #[DataProvider('assignableRoles')]
    public function test_technicians_and_viewers_see_only_batches_of_assigned_farms(Role $role): void
    {
        $this->actingAs($this->userFor($role))
            ->get(route('batches.index'))
            ->assertOk()
            ->assertSee('ASIG-1')
            ->assertDontSee('HERM-1')
            ->assertDontSee('AJENO-1');

        $this->get(route('batches.show', $this->assignedBatch))->assertOk();
        $this->get(route('batches.show', $this->siblingBatch))->assertForbidden();
        $this->get(route('batches.show', $this->foreignBatch))->assertForbidden();
    }

    #[DataProvider('assignableRoles')]
    public function test_unassigned_technicians_and_viewers_see_no_batches(Role $role): void
    {
        $this->actingAs(User::factory()->role($role)->forProducer($this->producer)->create())
            ->get(route('batches.index'))
            ->assertOk()
            ->assertSee('No tienes lotes disponibles');

        $this->get(route('batches.show', $this->assignedBatch))->assertForbidden();
    }

    public function test_access_follows_farm_assignment_changes(): void
    {
        $technician = $this->userFor(Role::Technician);

        $this->actingAs($technician)->get(route('batches.show', $this->assignedBatch))->assertOk();

        $this->assignedFarm->users()->detach($technician);

        $this->get(route('batches.show', $this->assignedBatch))->assertForbidden();
    }

    public function test_a_producer_without_producer_cannot_see_batches(): void
    {
        $this->actingAs(User::factory()->role(Role::Producer)->create());

        $this->get(route('batches.index'))->assertForbidden();
        $this->get(route('batches.show', $this->assignedBatch))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee(route('batches.index'));
    }

    public function test_menu_shows_batches_to_users_with_access(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get(route('dashboard'))->assertSee(route('batches.index'));

        $this->actingAs($this->userFor(Role::Viewer))
            ->get(route('dashboard'))->assertSee(route('batches.index'));
    }

    public function test_non_numeric_ids_are_not_found(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())->get('/lotes/abc')->assertNotFound();
    }
}
