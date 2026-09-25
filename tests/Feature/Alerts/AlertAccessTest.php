<?php

namespace Tests\Feature\Alerts;

use App\Enums\Role;
use App\Models\Alert;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Metric;
use App\Models\Pond;
use App\Models\Producer;
use App\Models\User;
use App\Models\WaterQualityReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AlertAccessTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private Farm $assignedFarm;

    private Alert $assignedAlert;

    private Alert $siblingAlert;

    private Alert $foreignAlert;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->assignedFarm = Farm::factory()->for($this->producer)->create();
        $siblingFarm = Farm::factory()->for($this->producer)->create();

        $this->assignedAlert = $this->alertFor($this->assignedFarm, 'ASIG');
        $this->siblingAlert = $this->alertFor($siblingFarm, 'HERM');
        $this->foreignAlert = $this->alertFor(Farm::factory()->create(), 'AJENO');
    }

    private function alertFor(Farm $farm, string $code): Alert
    {
        $batch = Batch::factory()->for(Pond::factory()->for($farm))->create(['code' => $code]);
        $metric = Metric::factory()->create();
        $reading = WaterQualityReading::factory()->for($batch)->for($metric, 'metric')->create();

        return Alert::factory()->create([
            'batch_id' => $batch->id, 'metric_id' => $metric->id, 'water_quality_reading_id' => $reading->id,
        ]);
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

    private function userFor(Role $role): User
    {
        $user = User::factory()->role($role)->forProducer($this->producer)->create();

        if ($role !== Role::Producer) {
            $this->assignedFarm->users()->attach($user);
        }

        return $user;
    }

    public function test_guests_cannot_access_alerts(): void
    {
        $this->get(route('alerts.index'))->assertRedirect(route('login'));
        $this->patch(route('alerts.acknowledge', $this->assignedAlert))->assertRedirect(route('login'));
        $this->patch(route('alerts.resolve', $this->assignedAlert))->assertRedirect(route('login'));
    }

    public function test_producer_sees_alerts_of_all_their_farms_only(): void
    {
        $this->actingAs($this->userFor(Role::Producer))
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('ASIG')
            ->assertSee('HERM')
            ->assertDontSee('AJENO');

        $this->patch(route('alerts.acknowledge', $this->siblingAlert))->assertSessionHasNoErrors();
        $this->patch(route('alerts.acknowledge', $this->foreignAlert))->assertForbidden();
        $this->patch(route('alerts.resolve', $this->foreignAlert))->assertForbidden();
    }

    #[DataProvider('assignableRoles')]
    public function test_technicians_and_viewers_see_only_alerts_of_assigned_farms(Role $role): void
    {
        $this->actingAs($this->userFor($role))
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertSee('ASIG')
            ->assertDontSee('HERM')
            ->assertDontSee('AJENO');
    }

    public function test_technicians_can_manage_alerts_of_assigned_farms(): void
    {
        $technician = $this->userFor(Role::Technician);

        $this->actingAs($technician)->patch(route('alerts.acknowledge', $this->assignedAlert))->assertSessionHasNoErrors();
        $this->patch(route('alerts.resolve', $this->siblingAlert))->assertForbidden();
        $this->patch(route('alerts.acknowledge', $this->foreignAlert))->assertForbidden();

        // Al quitar la asignación, pierde también el acceso a la alerta.
        $this->assignedFarm->users()->detach($technician);
        $this->get(route('alerts.index'))->assertOk()->assertDontSee('ASIG');
    }

    public function test_viewers_can_only_read(): void
    {
        $viewer = $this->userFor(Role::Viewer);

        $this->actingAs($viewer)
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertDontSee('Reconocer')
            ->assertDontSee('Resolver');

        $this->patch(route('alerts.acknowledge', $this->assignedAlert))->assertForbidden();
        $this->patch(route('alerts.resolve', $this->assignedAlert))->assertForbidden();

        $this->assertTrue($this->assignedAlert->fresh()->isPending());
    }

    public function test_a_producer_without_producer_cannot_see_alerts(): void
    {
        $this->actingAs(User::factory()->role(Role::Producer)->create());

        $this->get(route('alerts.index'))->assertForbidden();
        $this->patch(route('alerts.acknowledge', $this->assignedAlert))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee(route('alerts.index'));
    }

    public function test_inactive_users_are_redirected_to_login(): void
    {
        $technician = $this->userFor(Role::Technician);
        $technician->update(['is_active' => false]);

        $this->actingAs($technician)->get(route('alerts.index'))->assertRedirect(route('login'));
    }

    public function test_menu_shows_alerts_to_users_with_access(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get(route('dashboard'))->assertSee(route('alerts.index'));

        $this->actingAs($this->userFor(Role::Viewer))
            ->get(route('dashboard'))->assertSee(route('alerts.index'));
    }

    public function test_non_numeric_alert_ids_are_not_found(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->patch('/alertas/abc/reconocer')
            ->assertNotFound();
    }
}
