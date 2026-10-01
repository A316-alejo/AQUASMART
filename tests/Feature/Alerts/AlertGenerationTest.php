<?php

namespace Tests\Feature\Alerts;

use App\Enums\AlertStatus;
use App\Enums\Role;
use App\Enums\WaterQualityStatus;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Metric;
use App\Models\Pond;
use App\Models\User;
use App\Models\WaterQualityReading;
use App\Services\AlertGenerator;
use App\Services\WaterQualityEvaluator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Farm $farm;

    private Batch $batch;

    private Metric $metric;

    private AlertGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->farm = Farm::factory()->create();
        $this->batch = Batch::factory()->for(Pond::factory()->for($this->farm))->create([
            'stocked_at' => today()->subDays(10)->toDateString(),
        ]);
        $this->metric = Metric::factory()->create(['min_value' => 0, 'max_value' => 40]);
        $this->generator = new AlertGenerator(new WaterQualityEvaluator);

        AlertRule::factory()->for($this->farm)->for($this->metric)->create([
            'warn_min' => 20, 'warn_max' => 30, 'crit_min' => 15, 'crit_max' => 35,
        ]);
    }

    private function reading(float $value): WaterQualityReading
    {
        $reading = WaterQualityReading::factory()->for($this->batch)->for($this->metric, 'metric')->create(['value' => $value]);

        return $reading->load(['batch.pond.farm', 'metric']);
    }

    public function test_a_warning_reading_generates_a_pending_warning_alert(): void
    {
        $reading = $this->reading(30.0);

        $alert = $this->generator->generateFor($reading);

        $this->assertNotNull($alert);
        $this->assertTrue($alert->reading->is($reading));
        $this->assertTrue($alert->batch->is($this->batch));
        $this->assertTrue($alert->metric->is($this->metric));
        $this->assertSame('30.000', $alert->value);
        $this->assertSame(WaterQualityStatus::Warning, $alert->severity);
        $this->assertSame(AlertStatus::Pending, $alert->status);
        $this->assertSame(1, Alert::count());
    }

    public function test_a_critical_reading_generates_a_pending_critical_alert(): void
    {
        $alert = $this->generator->generateFor($this->reading(36.0));

        $this->assertSame(WaterQualityStatus::Critical, $alert->severity);
        $this->assertSame(AlertStatus::Pending, $alert->status);
    }

    public function test_a_normal_reading_generates_no_alert(): void
    {
        $alert = $this->generator->generateFor($this->reading(25.0));

        $this->assertNull($alert);
        $this->assertSame(0, Alert::count());
    }

    public function test_the_same_reading_never_produces_more_than_one_alert(): void
    {
        $reading = $this->reading(36.0);

        $first = $this->generator->generateFor($reading);
        $second = $this->generator->generateFor($reading);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Alert::count());
    }

    public function test_the_database_also_blocks_a_duplicate_alert_for_the_same_reading(): void
    {
        $reading = $this->reading(36.0);
        Alert::factory()->create([
            'batch_id' => $this->batch->id, 'metric_id' => $this->metric->id,
            'water_quality_reading_id' => $reading->id, 'value' => 36, 'severity' => WaterQualityStatus::Critical,
        ]);

        $this->expectException(QueryException::class);
        Alert::factory()->create([
            'batch_id' => $this->batch->id, 'metric_id' => $this->metric->id,
            'water_quality_reading_id' => $reading->id, 'value' => 36, 'severity' => WaterQualityStatus::Critical,
        ]);
    }

    public function test_an_alert_keeps_its_value_and_severity_if_the_rule_changes_later(): void
    {
        $alert = $this->generator->generateFor($this->reading(36.0));

        AlertRule::where('farm_id', $this->farm->id)->update(['crit_max' => 1000, 'warn_max' => 1000]);

        $alert->refresh();
        $this->assertSame(WaterQualityStatus::Critical, $alert->severity);
        $this->assertSame('36.000', $alert->value);
    }

    public function test_registering_a_reading_over_http_generates_the_alert_and_reports_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('batches.water-quality.store', $this->batch), [
                'water_quality_date' => now()->format('Y-m-d\TH:i'),
                'water_quality_metric_id' => $this->metric->id,
                'water_quality_value' => '36',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Se generó una alerta crítica.'));

        $alert = Alert::sole();
        $this->assertSame(WaterQualityStatus::Critical, $alert->severity);

        $this->actingAs($this->admin)
            ->post(route('batches.water-quality.store', $this->batch), [
                'water_quality_date' => now()->format('Y-m-d\TH:i'),
                'water_quality_metric_id' => $this->metric->id,
                'water_quality_value' => '25',
            ])
            ->assertSessionHas('status', fn (string $status) => ! str_contains($status, 'alerta'));

        $this->assertSame(1, Alert::count());
    }
}
