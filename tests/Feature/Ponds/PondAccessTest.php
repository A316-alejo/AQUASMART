<?php

namespace Tests\Feature\Ponds;

use App\Enums\PondType;
use App\Enums\Role;
use App\Models\Farm;
use App\Models\Pond;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PondAccessTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private Farm $assignedFarm;

    private Farm $siblingFarm;

    private Pond $assignedPond;

    private Pond $siblingPond;

    private Pond $foreignPond;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->assignedFarm = Farm::factory()->for($this->producer)->create();
        $this->siblingFarm = Farm::factory()->for($this->producer)->create();

        $this->assignedPond = Pond::factory()->for($this->assignedFarm)->create(['code' => 'ASIG-1']);
        $this->siblingPond = Pond::factory()->for($this->siblingFarm)->create(['code' => 'HERM-1']);
        $this->foreignPond = Pond::factory()->create(['code' => 'AJENO-1']);
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
    private function adminRoutes(Pond $pond): array
    {
        return [
            ['get', route('ponds.create')],
            ['post', route('ponds.store')],
            ['get', route('ponds.edit', $pond)],
            ['put', route('ponds.update', $pond)],
            ['get', route('ponds.deactivate.confirm', $pond)],
            ['patch', route('ponds.deactivate', $pond)],
            ['patch', route('ponds.activate', $pond)],
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

    public function test_guests_cannot_access_ponds(): void
    {
        $routes = [
            ['get', route('ponds.index')],
            ['get', route('ponds.show', $this->assignedPond)],
            ...$this->adminRoutes($this->assignedPond),
        ];

        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_run_administrative_actions(Role $role): void
    {
        $user = $this->userFor($role);
        $inactive = Pond::factory()->for($this->assignedFarm)->inactive()->create();

        foreach ([$this->assignedPond, $inactive] as $pond) {
            foreach ($this->adminRoutes($pond) as [$method, $url]) {
                $this->actingAs($user)->{$method}($url, [
                    'farm_id' => $this->assignedFarm->id, 'code' => 'HACK', 'name' => 'Cambio',
                    'type' => PondType::Tank->value, 'surface_m2' => '10', 'is_active' => '1',
                ])->assertForbidden();
            }
        }

        $this->assertDatabaseMissing('ponds', ['code' => 'HACK']);
        $this->assertTrue($this->assignedPond->fresh()->is_active);
        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_producer_sees_ponds_of_all_their_farms_only(): void
    {
        $this->actingAs($this->userFor(Role::Producer))
            ->get(route('ponds.index'))
            ->assertOk()
            ->assertSee('ASIG-1')
            ->assertSee('HERM-1')
            ->assertDontSee('AJENO-1')
            ->assertDontSee('Nuevo estanque')
            ->assertDontSee($this->foreignPond->farm->name);

        $this->get(route('ponds.show', $this->siblingPond))->assertOk()->assertDontSee(route('ponds.edit', $this->siblingPond));
        $this->get(route('ponds.show', $this->foreignPond))->assertForbidden();
        $this->get(route('ponds.index', ['farm' => $this->foreignPond->farm_id]))->assertDontSee('AJENO-1');
    }

    #[DataProvider('assignableRoles')]
    public function test_technicians_and_viewers_see_only_ponds_of_assigned_farms(Role $role): void
    {
        $this->actingAs($this->userFor($role))
            ->get(route('ponds.index'))
            ->assertOk()
            ->assertSee('ASIG-1')
            ->assertDontSee('HERM-1')
            ->assertDontSee('AJENO-1');

        $this->get(route('ponds.show', $this->assignedPond))->assertOk();
        $this->get(route('ponds.show', $this->siblingPond))->assertForbidden();
        $this->get(route('ponds.show', $this->foreignPond))->assertForbidden();
    }

    #[DataProvider('assignableRoles')]
    public function test_unassigned_technicians_and_viewers_see_no_ponds(Role $role): void
    {
        $this->actingAs(User::factory()->role($role)->forProducer($this->producer)->create())
            ->get(route('ponds.index'))
            ->assertOk()
            ->assertSee('No tienes estanques disponibles');

        $this->get(route('ponds.show', $this->assignedPond))->assertForbidden();
    }

    public function test_access_follows_farm_assignment_changes(): void
    {
        $technician = $this->userFor(Role::Technician);

        $this->actingAs($technician)->get(route('ponds.show', $this->assignedPond))->assertOk();

        $this->assignedFarm->users()->detach($technician);

        $this->get(route('ponds.show', $this->assignedPond))->assertForbidden();
    }

    public function test_a_producer_without_producer_cannot_see_ponds(): void
    {
        $this->actingAs(User::factory()->role(Role::Producer)->create());

        $this->get(route('ponds.index'))->assertForbidden();
        $this->get(route('ponds.show', $this->assignedPond))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee(route('ponds.index'));
    }

    public function test_menu_shows_ponds_to_users_with_access(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get(route('dashboard'))->assertSee(route('ponds.index'));

        $this->actingAs($this->userFor(Role::Technician))
            ->get(route('dashboard'))->assertSee(route('ponds.index'));
    }

    public function test_non_numeric_ids_are_not_found(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())->get('/estanques/abc')->assertNotFound();
    }
}
