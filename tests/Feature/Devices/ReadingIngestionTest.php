<?php

namespace Tests\Feature\Devices;

use App\Enums\AlertStatus;
use App\Enums\BatchStatus;
use App\Models\AlertRule;
use App\Models\Batch;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Metric;
use App\Models\Pond;
use App\Models\Sensor;
use App\Models\WaterQualityReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadingIngestionTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    private Sensor $sensor;

    private Batch $batch;

    private Metric $metric;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $farm = Farm::factory()->create();
        $pond = Pond::factory()->for($farm)->create();
        $this->batch = Batch::factory()->for($pond)->create(['stocked_at' => today()->subDays(30)]);
        $this->metric = Metric::factory()->create(['name' => 'pH', 'min_value' => 0, 'max_value' => 14]);
        $this->device = Device::factory()->for($farm)->create();
        $this->token = $this->device->generateToken();
        $this->sensor = Sensor::factory()->for($this->device)->for($pond)->for($this->metric, 'metric')->create(['code' => 'S01']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ingest(array $payload, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/readings', $payload, [
            'Authorization' => 'Bearer '.($token ?? $this->token),
        ]);
    }

    public function test_requests_without_a_valid_token_are_rejected(): void
    {
        $payload = ['sensor_code' => 'S01', 'measured_at' => now()->toIso8601String(), 'value' => 7.2];

        $this->postJson('/api/v1/readings', $payload)->assertStatus(401);
        $this->ingest($payload, 'token-invalido')->assertStatus(401);

        $this->assertSame(0, WaterQualityReading::count());
    }

    public function test_an_inactive_devices_token_is_rejected(): void
    {
        $this->device->update(['is_active' => false]);

        $this->ingest(['sensor_code' => 'S01', 'measured_at' => now()->toIso8601String(), 'value' => 7.2])
            ->assertStatus(401);
    }

    public function test_a_single_unwrapped_reading_is_accepted(): void
    {
        $this->ingest(['sensor_code' => 'S01', 'measured_at' => now()->toIso8601String(), 'value' => 7.2])
            ->assertCreated()
            ->assertJson(['created' => 1, 'alerts' => []]);

        $reading = WaterQualityReading::firstOrFail();
        $this->assertSame('sensor', $reading->source);
        $this->assertTrue($reading->batch->is($this->batch));
        $this->assertSame('7.200', $reading->value);
        $this->assertTrue($this->device->fresh()->last_connected_at !== null);
    }

    public function test_several_readings_are_accepted_in_one_request(): void
    {
        $metric2 = Metric::factory()->create(['name' => 'Oxígeno disuelto', 'min_value' => 0, 'max_value' => 20]);
        $sensor2 = Sensor::factory()->for($this->device)->for($this->sensor->pond)->for($metric2, 'metric')->create(['code' => 'S02']);

        $this->ingest([
            'readings' => [
                ['sensor_code' => 'S01', 'measured_at' => now()->subMinutes(5)->toIso8601String(), 'value' => 7.0],
                ['sensor_code' => 'S02', 'measured_at' => now()->toIso8601String(), 'value' => 6.5],
            ],
        ])->assertCreated()->assertJson(['created' => 2]);

        $this->assertSame(2, WaterQualityReading::count());
        $this->assertSame(1, WaterQualityReading::where('metric_id', $sensor2->metric_id)->count());
    }

    public function test_a_critical_reading_generates_an_alert(): void
    {
        AlertRule::factory()->for($this->sensor->pond->farm)->for($this->metric, 'metric')->create([
            'crit_max' => 9, 'warn_max' => 8,
        ]);

        $response = $this->ingest(['sensor_code' => 'S01', 'measured_at' => now()->toIso8601String(), 'value' => 9.5]);

        $response->assertCreated()->assertJsonPath('alerts.0.severity', 'critico');
        $this->assertDatabaseHas('alerts', ['status' => AlertStatus::Pending->value, 'severity' => 'critico']);
    }

    public function test_unknown_or_foreign_sensor_code_is_rejected(): void
    {
        $this->ingest(['sensor_code' => 'NO-EXISTE', 'measured_at' => now()->toIso8601String(), 'value' => 7.2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['readings.0.sensor_code']);

        $otherDevice = Device::factory()->create();
        $otherToken = $otherDevice->generateToken();

        $this->ingest(['sensor_code' => 'S01', 'measured_at' => now()->toIso8601String(), 'value' => 7.2], $otherToken)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['readings.0.sensor_code']);

        $this->assertSame(0, WaterQualityReading::count());
    }

    public function test_an_inactive_sensor_is_rejected(): void
    {
        $this->sensor->update(['is_active' => false]);

        $this->ingest(['sensor_code' => 'S01', 'measured_at' => now()->toIso8601String(), 'value' => 7.2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['readings.0.sensor_code']);
    }

    public function test_a_pond_without_an_active_batch_is_rejected(): void
    {
        $this->batch->close(BatchStatus::Closed, today());

        $this->ingest(['sensor_code' => 'S01', 'measured_at' => now()->toIso8601String(), 'value' => 7.2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['readings.0.sensor_code']);

        $this->assertSame(0, WaterQualityReading::count());
    }

    public function test_value_outside_the_physical_range_is_rejected(): void
    {
        $this->ingest(['sensor_code' => 'S01', 'measured_at' => now()->toIso8601String(), 'value' => 50])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['readings.0.value']);
    }

    public function test_dates_before_stocking_or_in_the_future_are_rejected(): void
    {
        $this->ingest(['sensor_code' => 'S01', 'measured_at' => today()->subDays(60)->toIso8601String(), 'value' => 7.2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['readings.0.measured_at']);

        $this->ingest(['sensor_code' => 'S01', 'measured_at' => now()->addHours(2)->toIso8601String(), 'value' => 7.2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['readings.0.measured_at']);

        $this->assertSame(0, WaterQualityReading::count());
    }

    public function test_the_whole_batch_is_rejected_if_one_reading_is_invalid(): void
    {
        $this->ingest([
            'readings' => [
                ['sensor_code' => 'S01', 'measured_at' => now()->toIso8601String(), 'value' => 7.2],
                ['sensor_code' => 'NO-EXISTE', 'measured_at' => now()->toIso8601String(), 'value' => 7.2],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, WaterQualityReading::count());
    }

    public function test_ingestion_does_not_run_n_plus_one_queries_per_reading(): void
    {
        $sensors = Sensor::factory()->count(5)->for($this->device)->for($this->sensor->pond)->for($this->metric, 'metric')->create();

        $count = function (array $readings): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->ingest(['readings' => $readings])->assertCreated();

            return count(DB::getQueryLog());
        };

        $one = $count([['sensor_code' => 'S01', 'measured_at' => now()->toIso8601String(), 'value' => 7.1]]);

        $five = $count($sensors->map(fn (Sensor $sensor) => [
            'sensor_code' => $sensor->code, 'measured_at' => now()->toIso8601String(), 'value' => 7.1,
        ])->all());

        $this->assertLessThan($one * 3, $five);
    }
}
