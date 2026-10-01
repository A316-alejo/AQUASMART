<?php

namespace Tests\Feature\Devices;

use App\Enums\BatchStatus;
use App\Enums\Role;
use App\Models\AlertRule;
use App\Models\Batch;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Metric;
use App\Models\Pond;
use App\Models\Producer;
use App\Models\Sensor;
use App\Models\User;
use App\Models\WaterQualityReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PondReadingsTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private Farm $farm;

    private Pond $pond;

    private Batch $batch;

    private Device $device;

    private Metric $metric;

    private Sensor $sensor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->farm = Farm::factory()->for($this->producer)->create();
        $this->pond = Pond::factory()->for($this->farm)->create(['code' => 'E01']);
        $this->batch = Batch::factory()->for($this->pond)->create(['stocked_at' => today()->subDays(30)]);
        $this->metric = Metric::factory()->create(['name' => 'pH', 'unit' => '', 'decimals' => 2, 'min_value' => 0, 'max_value' => 14]);
        $this->device = Device::factory()->for($this->farm)->create(['code' => 'DEV-01']);
        $this->sensor = Sensor::factory()->for($this->device)->for($this->pond)->for($this->metric, 'metric')->create(['code' => 'S01']);
    }

    private function admin(): User
    {
        return User::factory()->role(Role::Admin)->create();
    }

    public function test_guests_cannot_access_the_endpoint(): void
    {
        $this->get(route('ponds.readings', $this->pond))->assertRedirect(route('login'));
    }

    public function test_inactive_users_are_redirected_to_login(): void
    {
        $user = User::factory()->role(Role::Admin)->create(['is_active' => false]);

        $this->actingAs($user)->get(route('ponds.readings', $this->pond))->assertRedirect(route('login'));
    }

    public function test_a_foreign_pond_responds_with_403(): void
    {
        $producer = User::factory()->role(Role::Producer)->forProducer($this->producer)->create();
        $foreignPond = Pond::factory()->create();

        $this->actingAs($producer)->getJson(route('ponds.readings', $foreignPond))->assertForbidden();
    }

    public function test_returns_the_latest_reading_per_sensor_with_its_status(): void
    {
        AlertRule::factory()->for($this->farm)->for($this->metric, 'metric')->create(['warn_max' => 8, 'crit_max' => 9]);

        WaterQualityReading::factory()->for($this->batch)->for($this->metric, 'metric')
            ->create(['value' => 7.0, 'measured_at' => now()->subHour()]);
        WaterQualityReading::factory()->for($this->batch)->for($this->metric, 'metric')
            ->create(['value' => 8.5, 'measured_at' => now()->subMinutes(5)]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('ponds.readings', $this->pond))
            ->assertOk()
            ->assertJsonPath('pond_code', 'E01')
            ->assertJsonPath('has_active_batch', true)
            ->assertJsonCount(1, 'readings');

        $reading = $response->json('readings.0');

        $this->assertSame('S01', $reading['sensor_code']);
        $this->assertSame('pH', $reading['metric_name']);
        $this->assertSame('8,50', $reading['value']);
        $this->assertSame('advertencia', $reading['status']);
        $this->assertSame('DEV-01', $reading['device_code']);
        $this->assertTrue($reading['device_active']);
    }

    public function test_a_sensor_without_readings_yet_is_reported_gracefully(): void
    {
        $response = $this->actingAs($this->admin())
            ->getJson(route('ponds.readings', $this->pond))
            ->assertOk()
            ->assertJsonCount(1, 'readings');

        $reading = $response->json('readings.0');

        $this->assertNull($reading['value']);
        $this->assertNull($reading['measured_at']);
        $this->assertNull($reading['status']);
    }

    public function test_pond_without_an_active_batch_reports_it_and_has_no_readings(): void
    {
        $this->batch->close(BatchStatus::Closed, today());

        $this->actingAs($this->admin())
            ->getJson(route('ponds.readings', $this->pond))
            ->assertOk()
            ->assertJsonPath('has_active_batch', false);
    }

    public function test_an_inactive_sensor_is_excluded(): void
    {
        $this->sensor->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->getJson(route('ponds.readings', $this->pond))
            ->assertOk()
            ->assertJsonCount(0, 'readings');
    }

    public function test_an_inactive_device_still_reports_its_sensors_last_reading_but_flags_the_connection(): void
    {
        WaterQualityReading::factory()->for($this->batch)->for($this->metric, 'metric')->create(['value' => 7.1]);
        $this->device->update(['is_active' => false]);

        $response = $this->actingAs($this->admin())
            ->getJson(route('ponds.readings', $this->pond))
            ->assertOk()
            ->assertJsonCount(1, 'readings');

        $reading = $response->json('readings.0');
        $this->assertNotNull($reading['value']);
        $this->assertFalse($reading['device_active']);
    }

    public function test_producer_sees_only_readings_of_their_own_farms(): void
    {
        $owner = User::factory()->role(Role::Producer)->forProducer($this->producer)->create();
        $foreignPond = Pond::factory()->create();

        $this->actingAs($owner)->getJson(route('ponds.readings', $this->pond))->assertOk();
        $this->actingAs($owner)->getJson(route('ponds.readings', $foreignPond))->assertForbidden();
    }

    public function test_technician_sees_readings_only_for_assigned_farms(): void
    {
        $technician = User::factory()->role(Role::Technician)->forProducer($this->producer)->create();

        $this->actingAs($technician)->getJson(route('ponds.readings', $this->pond))->assertForbidden();

        $this->farm->users()->attach($technician);
        $this->actingAs($technician)->getJson(route('ponds.readings', $this->pond))->assertOk();

        $this->farm->users()->detach($technician);
        $this->actingAs($technician)->getJson(route('ponds.readings', $this->pond))->assertForbidden();
    }

    public function test_viewer_can_read_and_the_endpoint_only_accepts_get(): void
    {
        $viewer = User::factory()->role(Role::Viewer)->forProducer($this->producer)->create();
        $this->farm->users()->attach($viewer);

        $this->actingAs($viewer)->getJson(route('ponds.readings', $this->pond))->assertOk();
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('ponds.readings')->methods());
    }

    public function test_multiple_sensors_do_not_run_a_query_per_sensor(): void
    {
        $otherMetric = Metric::factory()->create(['name' => 'Oxígeno', 'min_value' => 0, 'max_value' => 20]);
        Sensor::factory()->count(7)->for($this->device)->for($this->pond)->for($otherMetric, 'metric')->create();
        WaterQualityReading::factory()->for($this->batch)->for($this->metric, 'metric')->create();
        WaterQualityReading::factory()->for($this->batch)->for($otherMetric, 'metric')->create();

        $this->actingAs($this->admin());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(route('ponds.readings', $this->pond))->assertOk()->assertJsonCount(8, 'readings');
        $queries = count(DB::getQueryLog());

        Sensor::factory()->count(10)->for($this->device)->for($this->pond)->for($otherMetric, 'metric')->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(route('ponds.readings', $this->pond))->assertOk()->assertJsonCount(18, 'readings');
        $this->assertSame($queries, count(DB::getQueryLog()));
    }

    public function test_the_response_is_json(): void
    {
        $this->actingAs($this->admin())
            ->get(route('ponds.readings', $this->pond))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');
    }
}
