<?php

namespace Tests\Feature\Alerts;

use App\Enums\AlertStatus;
use App\Enums\Role;
use App\Enums\WaterQualityStatus;
use App\Models\Alert;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Metric;
use App\Models\Pond;
use App\Models\User;
use App\Models\WaterQualityReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlertManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /**
     * Crea una alerta con un lote, finca y parámetro coherentes entre sí.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeAlert(array $overrides = []): Alert
    {
        $farm = $overrides['farm'] ?? Farm::factory()->create();
        $batch = $overrides['batch'] ?? Batch::factory()->for(Pond::factory()->for($farm))->create();
        $metric = Metric::factory()->create();
        $reading = WaterQualityReading::factory()->for($batch)->for($metric, 'metric')->create();

        return Alert::factory()->create([
            'batch_id' => $batch->id,
            'metric_id' => $metric->id,
            'water_quality_reading_id' => $reading->id,
            'value' => $reading->value,
            ...collect($overrides)->except(['farm', 'batch'])->all(),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(Role::Admin)->create();
    }

    public function test_admin_can_acknowledge_and_then_resolve_an_alert(): void
    {
        $alert = $this->makeAlert();

        $this->actingAs($this->admin)
            ->patch(route('alerts.acknowledge', $alert))
            ->assertRedirect(route('alerts.index'))
            ->assertSessionHas('status', 'Alerta reconocida.');

        $alert->refresh();
        $this->assertSame(AlertStatus::Acknowledged, $alert->status);
        $this->assertNotNull($alert->acknowledged_at);
        $this->assertTrue($alert->acknowledgedBy->is($this->admin));

        $this->patch(route('alerts.resolve', $alert))
            ->assertSessionHas('status', 'Alerta resuelta.');

        $alert->refresh();
        $this->assertSame(AlertStatus::Resolved, $alert->status);
        $this->assertNotNull($alert->resolved_at);
        $this->assertTrue($alert->resolvedBy->is($this->admin));
    }

    public function test_an_alert_can_be_resolved_directly_without_acknowledging_it_first(): void
    {
        $alert = $this->makeAlert();

        $this->actingAs($this->admin)->patch(route('alerts.resolve', $alert))->assertSessionHasNoErrors();

        $alert->refresh();
        $this->assertSame(AlertStatus::Resolved, $alert->status);
        $this->assertNull($alert->acknowledged_at);
    }

    public function test_an_already_acknowledged_or_resolved_alert_cannot_be_acknowledged_again(): void
    {
        $acknowledged = $this->makeAlert(['status' => AlertStatus::Acknowledged, 'acknowledged_at' => now()]);
        $resolved = $this->makeAlert(['status' => AlertStatus::Resolved, 'resolved_at' => now()]);

        $this->actingAs($this->admin);
        $this->patch(route('alerts.acknowledge', $acknowledged))->assertForbidden();
        $this->patch(route('alerts.acknowledge', $resolved))->assertForbidden();
        $this->patch(route('alerts.resolve', $resolved))->assertForbidden();
    }

    public function test_alerts_are_listed_with_severity_and_status(): void
    {
        $warning = $this->makeAlert(['severity' => WaterQualityStatus::Warning]);
        $critical = $this->makeAlert(['severity' => WaterQualityStatus::Critical, 'status' => AlertStatus::Resolved, 'resolved_at' => now()]);

        $this->actingAs($this->admin)
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertSee($warning->batch->code)
            ->assertSee($critical->batch->code)
            ->assertSee('Advertencia')
            ->assertSee('Crítico')
            ->assertSee('Pendiente')
            ->assertSee('Resuelta')
            ->assertSee('Reconocer')
            ->assertSee('Resolver');
    }

    public function test_filters_by_farm_batch_metric_severity_status_and_date(): void
    {
        $farmA = Farm::factory()->create();
        $farmB = Farm::factory()->create();
        $batchA = Batch::factory()->for(Pond::factory()->for($farmA))->create(['code' => 'L-A']);
        $batchB = Batch::factory()->for(Pond::factory()->for($farmB))->create(['code' => 'L-B']);
        $metricA = Metric::factory()->create(['name' => 'Oxígeno']);
        $metricB = Metric::factory()->create(['name' => 'pH']);

        // Los desplegables de finca/lote/parámetro listan siempre todas las opciones disponibles
        // (para poder filtrar por cualquiera de ellas); por eso las comprobaciones de "no debe verse"
        // usan el valor numérico de cada alerta, que no se repite en ningún filtro.
        $v = fn (float $n) => number_format($n, 2, ',', '.').' mg/L';

        $target = $this->makeAlert([
            'farm' => $farmA, 'batch' => $batchA, 'severity' => WaterQualityStatus::Critical, 'status' => AlertStatus::Pending, 'value' => 111.5,
        ]);
        $target->forceFill(['metric_id' => $metricA->id, 'created_at' => today()->subDays(3)])->save();

        $other1 = $this->makeAlert(['farm' => $farmB, 'batch' => $batchB, 'severity' => WaterQualityStatus::Warning, 'value' => 222.5]);
        $other1->forceFill(['metric_id' => $metricB->id])->save();

        $other2 = $this->makeAlert([
            'farm' => $farmA, 'batch' => $batchA, 'severity' => WaterQualityStatus::Warning,
            'status' => AlertStatus::Resolved, 'resolved_at' => now(), 'value' => 333.5,
        ]);
        $other2->forceFill(['metric_id' => $metricA->id, 'created_at' => today()])->save();

        $this->actingAs($this->admin);

        $this->get(route('alerts.index', ['farm' => $farmA->id]))->assertSee($v(111.5))->assertDontSee($v(222.5));
        $this->get(route('alerts.index', ['batch' => $batchA->id]))->assertSee($v(111.5))->assertDontSee($v(222.5));
        $this->get(route('alerts.index', ['metric' => $metricB->id]))->assertSee($v(222.5))->assertDontSee($v(111.5));
        $this->get(route('alerts.index', ['severity' => 'critico']))->assertSee($v(111.5))->assertDontSee($v(222.5));
        $this->get(route('alerts.index', ['status' => 'resuelta']))->assertSee($v(333.5))->assertDontSee($v(222.5));
        $this->get(route('alerts.index', ['from' => today()->subDays(1)->toDateString()]))->assertSee($v(222.5))->assertDontSee($v(111.5));
        $this->get(route('alerts.index', ['to' => today()->subDays(1)->toDateString()]))->assertSee($v(111.5))->assertDontSee($v(222.5));

        // Los chips de vista aplican el mismo filtro que el formulario.
        $this->get(route('alerts.index', ['view' => 'pending']))->assertSee($v(111.5))->assertDontSee($v(333.5));
        $this->get(route('alerts.index', ['view' => 'resolved']))->assertSee($v(333.5))->assertDontSee($v(111.5));
    }

    public function test_alerts_are_paginated(): void
    {
        $farm = Farm::factory()->create();
        $batch = Batch::factory()->for(Pond::factory()->for($farm))->create();

        foreach (range(1, 17) as $i) {
            $metric = Metric::factory()->create();
            $reading = WaterQualityReading::factory()->for($batch)->for($metric, 'metric')->create();
            Alert::factory()->create([
                'batch_id' => $batch->id, 'metric_id' => $metric->id, 'water_quality_reading_id' => $reading->id,
                'value' => 100 + $i, 'created_at' => today()->subMinutes($i),
            ]);
        }

        $v = fn (int $n) => number_format($n, 2, ',', '.').' mg/L';

        $this->actingAs($this->admin);
        $this->get(route('alerts.index'))->assertSee($v(101))->assertDontSee($v(116))->assertSee('Mostrando 1–15 de 17');
        $this->get(route('alerts.index', ['page' => 2]))->assertSee($v(116))->assertSee($v(117));
    }

    public function test_listing_does_not_run_n_plus_one_queries(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('alerts.index'))->assertOk();

            return count(DB::getQueryLog());
        };

        $this->actingAs($this->admin);
        $this->makeAlert();
        $this->makeAlert();
        $few = $count();

        foreach (range(1, 10) as $i) {
            $this->makeAlert();
        }

        $this->assertSame($few, $count());
    }
}
