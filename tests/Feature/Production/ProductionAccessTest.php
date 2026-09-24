<?php

namespace Tests\Feature\Production;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Feeding;
use App\Models\FeedType;
use App\Models\Mortality;
use App\Models\MortalityCause;
use App\Models\Pond;
use App\Models\Producer;
use App\Models\Sampling;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductionAccessTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private Farm $assignedFarm;

    private Batch $assignedBatch;

    private Batch $siblingBatch;

    private Batch $foreignBatch;

    private FeedType $feedType;

    private MortalityCause $cause;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->assignedFarm = Farm::factory()->for($this->producer)->create();
        $siblingFarm = Farm::factory()->for($this->producer)->create();

        $stocked = ['stocked_at' => today()->subDays(30)->toDateString(), 'initial_quantity' => 1000];
        $this->assignedBatch = Batch::factory()->for(Pond::factory()->for($this->assignedFarm))->create($stocked);
        $this->siblingBatch = Batch::factory()->for(Pond::factory()->for($siblingFarm))->create($stocked);
        $this->foreignBatch = Batch::factory()->create($stocked);

        $this->feedType = FeedType::factory()->create();
        $this->cause = MortalityCause::factory()->create();
    }

    private function userFor(Role $role): User
    {
        $user = User::factory()->role($role)->forProducer($this->producer)->create();

        if ($role !== Role::Producer) {
            $this->assignedFarm->users()->attach($user);
        }

        return $user;
    }

    /**
     * Las tres solicitudes de registro sobre un lote.
     *
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    private function recordRequests(Batch $batch): array
    {
        $date = today()->subDay()->toDateString();

        return [
            [route('batches.feedings.store', $batch), ['feeding_date' => $date, 'feed_type_id' => $this->feedType->id, 'feeding_quantity_g' => '1000']],
            [route('batches.mortalities.store', $batch), ['mortality_date' => $date, 'mortality_cause_id' => $this->cause->id, 'mortality_quantity' => '5']],
            [route('batches.samplings.store', $batch), ['sampling_date' => $date, 'sample_size' => '30', 'average_weight_g' => '40']],
        ];
    }

    private function recordCount(): int
    {
        return Feeding::count() + Mortality::count() + Sampling::count();
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function operativeRoles(): array
    {
        return [
            'productor' => [Role::Producer],
            'técnico' => [Role::Technician],
        ];
    }

    public function test_guests_cannot_record(): void
    {
        foreach ($this->recordRequests($this->assignedBatch) as [$url, $data]) {
            $this->post($url, $data)->assertRedirect(route('login'));
        }

        $this->assertSame(0, $this->recordCount());
    }

    #[DataProvider('operativeRoles')]
    public function test_producers_and_technicians_record_on_batches_they_can_access(Role $role): void
    {
        $user = $this->userFor($role);

        foreach ($this->recordRequests($this->assignedBatch) as [$url, $data]) {
            $this->actingAs($user)->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
        }

        $this->assertSame(3, $this->assignedBatch->feedings()->count() + $this->assignedBatch->mortalities()->count() + $this->assignedBatch->samplings()->count());
        $this->assertTrue(Feeding::sole()->user->is($user));

        $this->get(route('batches.show', $this->assignedBatch))
            ->assertOk()
            ->assertSee('Registrar alimentación')
            ->assertSee('Registrar mortalidad')
            ->assertSee('Registrar biometría');
    }

    public function test_producer_is_isolated_to_their_own_farms(): void
    {
        $producer = $this->userFor(Role::Producer);

        // Otra finca del mismo productor: permitido.
        [$url, $data] = $this->recordRequests($this->siblingBatch)[0];
        $this->actingAs($producer)->post($url, $data)->assertSessionHasNoErrors();

        // Lote de otro productor: 403 en consulta y registro.
        $this->get(route('batches.show', $this->foreignBatch))->assertForbidden();
        foreach ($this->recordRequests($this->foreignBatch) as [$url, $data]) {
            $this->post($url, $data)->assertForbidden();
        }

        $this->assertSame(0, $this->foreignBatch->feedings()->count() + $this->foreignBatch->mortalities()->count());
    }

    public function test_technicians_only_record_on_batches_of_assigned_farms(): void
    {
        $technician = $this->userFor(Role::Technician);

        foreach ([$this->siblingBatch, $this->foreignBatch] as $batch) {
            $this->actingAs($technician)->get(route('batches.show', $batch))->assertForbidden();

            foreach ($this->recordRequests($batch) as [$url, $data]) {
                $this->post($url, $data)->assertForbidden();
            }
        }

        $this->assertSame(0, $this->recordCount());

        // Al quitar la asignación, pierde también el permiso de registrar.
        $this->assignedFarm->users()->detach($technician);
        [$url, $data] = $this->recordRequests($this->assignedBatch)[0];
        $this->post($url, $data)->assertForbidden();
    }

    public function test_viewers_can_consult_but_not_record(): void
    {
        Feeding::factory()->for($this->assignedBatch)->for($this->feedType)->create(['notes' => 'Ración de la mañana']);
        $viewer = $this->userFor(Role::Viewer);

        $this->actingAs($viewer)
            ->get(route('batches.show', $this->assignedBatch))
            ->assertOk()
            ->assertSee('Ración de la mañana')
            ->assertSee('Biomasa estimada')
            ->assertDontSee('Registrar alimentación')
            ->assertDontSee('Registrar mortalidad')
            ->assertDontSee('Registrar biometría');

        foreach ($this->recordRequests($this->assignedBatch) as [$url, $data]) {
            $this->post($url, $data)->assertForbidden();
        }

        $this->assertSame(1, $this->recordCount());
    }

    public function test_unassigned_users_and_producers_without_producer_cannot_record(): void
    {
        $users = [
            User::factory()->role(Role::Technician)->forProducer($this->producer)->create(),
            User::factory()->role(Role::Producer)->create(),
        ];

        foreach ($users as $user) {
            foreach ($this->recordRequests($this->assignedBatch) as [$url, $data]) {
                $this->actingAs($user)->post($url, $data)->assertForbidden();
            }
        }

        $this->assertSame(0, $this->recordCount());
    }

    public function test_inactive_users_cannot_record(): void
    {
        $technician = $this->userFor(Role::Technician);
        $technician->update(['is_active' => false]);

        [$url, $data] = $this->recordRequests($this->assignedBatch)[0];
        $this->actingAs($technician)->post($url, $data)->assertRedirect(route('login'));

        $this->assertSame(0, $this->recordCount());
    }

    public function test_non_numeric_batch_ids_are_not_found(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->post('/lotes/abc/alimentacion', [])
            ->assertNotFound();
    }
}
