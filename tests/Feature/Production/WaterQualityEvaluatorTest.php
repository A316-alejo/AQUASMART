<?php

namespace Tests\Feature\Production;

use App\Enums\Role;
use App\Enums\WaterQualityStatus;
use App\Models\AlertRule;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Metric;
use App\Models\Pond;
use App\Models\User;
use App\Models\WaterQualityReading;
use App\Services\WaterQualityEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaterQualityEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private WaterQualityEvaluator $evaluator;

    private Metric $metric;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new WaterQualityEvaluator;
        $this->metric = Metric::factory()->create(['min_value' => 0, 'max_value' => 40]);
    }

    public function test_a_reading_is_normal_without_a_configured_rule(): void
    {
        $this->assertSame(WaterQualityStatus::Normal, $this->evaluator->evaluate($this->metric, 25.0, null));
    }

    public function test_a_reading_is_normal_when_the_rule_is_disabled(): void
    {
        $rule = AlertRule::factory()->for($this->metric)->disabled()->create(['crit_max' => 30]);

        $this->assertSame(WaterQualityStatus::Normal, $this->evaluator->evaluate($this->metric, 35.0, $rule));
    }

    public function test_classifies_normal_warning_and_critical_by_thresholds(): void
    {
        $rule = AlertRule::factory()->for($this->metric)->create([
            'warn_min' => 20, 'warn_max' => 30, 'crit_min' => 15, 'crit_max' => 35,
        ]);

        $this->assertSame(WaterQualityStatus::Normal, $this->evaluator->evaluate($this->metric, 25.0, $rule));
        $this->assertSame(WaterQualityStatus::Warning, $this->evaluator->evaluate($this->metric, 20.0, $rule));
        $this->assertSame(WaterQualityStatus::Warning, $this->evaluator->evaluate($this->metric, 30.0, $rule));
        $this->assertSame(WaterQualityStatus::Warning, $this->evaluator->evaluate($this->metric, 18.0, $rule));
        $this->assertSame(WaterQualityStatus::Critical, $this->evaluator->evaluate($this->metric, 15.0, $rule));
        $this->assertSame(WaterQualityStatus::Critical, $this->evaluator->evaluate($this->metric, 35.0, $rule));
        $this->assertSame(WaterQualityStatus::Critical, $this->evaluator->evaluate($this->metric, 5.0, $rule));
    }

    public function test_a_one_sided_threshold_only_evaluates_the_configured_side(): void
    {
        // Solo se configura un máximo de advertencia: valores bajos siempre son normales.
        $rule = AlertRule::factory()->for($this->metric)->create(['warn_max' => 30, 'crit_max' => 35]);

        $this->assertSame(WaterQualityStatus::Normal, $this->evaluator->evaluate($this->metric, 0.0, $rule));
        $this->assertSame(WaterQualityStatus::Warning, $this->evaluator->evaluate($this->metric, 30.0, $rule));
        $this->assertSame(WaterQualityStatus::Critical, $this->evaluator->evaluate($this->metric, 35.0, $rule));
    }

    public function test_rule_for_finds_the_matching_rule_in_a_preloaded_collection(): void
    {
        $farm = Farm::factory()->create();
        $other = Metric::factory()->create();
        $rule = AlertRule::factory()->for($farm)->for($this->metric)->create();
        AlertRule::factory()->for($farm)->for($other)->create();

        $rules = $farm->alertRules;

        $this->assertTrue($this->evaluator->ruleFor($rules, $this->metric->id)->is($rule));
        $this->assertNull($this->evaluator->ruleFor($rules, 999999));
    }

    public function test_batch_detail_shows_the_computed_status_per_reading(): void
    {
        $farm = Farm::factory()->create();
        $batch = Batch::factory()->for(Pond::factory()->for($farm))->create(['stocked_at' => today()->subDays(10)->toDateString()]);
        AlertRule::factory()->for($farm)->for($this->metric)->create(['warn_max' => 30, 'crit_max' => 35]);

        WaterQualityReading::factory()->for($batch)->for($this->metric, 'metric')->create(['value' => 10, 'notes' => 'Lectura normal']);
        WaterQualityReading::factory()->for($batch)->for($this->metric, 'metric')->create(['value' => 31, 'notes' => 'Lectura de advertencia']);
        WaterQualityReading::factory()->for($batch)->for($this->metric, 'metric')->create(['value' => 36, 'notes' => 'Lectura crítica']);

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get(route('batches.show', $batch))
            ->assertOk()
            ->assertSeeInOrder(['Crítico', 'Lectura crítica'])
            ->assertSeeInOrder(['Advertencia', 'Lectura de advertencia'])
            ->assertSeeInOrder(['Normal', 'Lectura normal']);
    }

    public function test_a_farms_rule_does_not_affect_another_farms_readings(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        $batchB = Batch::factory()->for(Pond::factory()->for($farmB))->create();

        // Umbral crítico muy bajo configurado solo en la finca A.
        AlertRule::factory()->for($farmA)->for($this->metric)->create(['crit_max' => 1]);
        WaterQualityReading::factory()->for($batchB)->for($this->metric, 'metric')->create(['value' => 30, 'notes' => 'Lectura de la finca B']);

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get(route('batches.show', $batchB))
            ->assertSeeInOrder(['Normal', 'Lectura de la finca B']);
    }
}
