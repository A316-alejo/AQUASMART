<?php

namespace Tests\Feature\Production;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Metric;
use App\Models\User;
use App\Models\WaterQualityReading;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WaterQualityRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Batch $batch;

    private Metric $metric;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(Role::Admin)->create(['name' => 'Ana Admin']);
        $this->batch = Batch::factory()->create([
            'code' => 'L-01',
            'stocked_at' => today()->subDays(30)->toDateString(),
        ]);
        $this->metric = Metric::factory()->create([
            'code' => 'ph', 'name' => 'pH', 'unit' => null, 'decimals' => 2, 'min_value' => 0, 'max_value' => 14,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reading(array $overrides = []): array
    {
        return [
            'water_quality_date' => today()->subDay()->format('Y-m-d').'T08:30',
            'water_quality_metric_id' => $this->metric->id,
            'water_quality_value' => '7,2',
            'water_quality_notes' => '  Agua clara.  ',
            ...$overrides,
        ];
    }

    public function test_reading_can_be_recorded_and_consulted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('batches.water-quality.store', $this->batch), $this->reading())
            ->assertRedirect(route('batches.show', $this->batch).'#water-quality')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Se registró la medición de calidad del agua del lote L-01.');

        $reading = WaterQualityReading::sole();
        $this->assertTrue($reading->batch->is($this->batch));
        $this->assertTrue($reading->metric->is($this->metric));
        $this->assertTrue($reading->user->is($this->admin));
        $this->assertSame('7.200', $reading->value);
        $this->assertSame('manual', $reading->source);
        $this->assertSame('Agua clara.', $reading->notes);
        $this->assertSame(today()->subDay()->format('Y-m-d').' 08:30:00', $reading->measured_at->format('Y-m-d H:i:s'));

        $this->get(route('batches.show', $this->batch))
            ->assertOk()
            ->assertSee('Calidad del agua')
            ->assertSee('pH')
            ->assertSee('7,20')
            ->assertSee('Ana Admin')
            ->assertSee('Agua clara.');
    }

    public function test_reading_rejects_a_nonexistent_or_inactive_parameter(): void
    {
        $inactive = Metric::factory()->inactive()->create();

        $this->actingAs($this->admin)
            ->post(route('batches.water-quality.store', $this->batch), $this->reading(['water_quality_metric_id' => $inactive->id]))
            ->assertSessionHasErrors(['water_quality_metric_id' => 'El parámetro seleccionado no existe o está inactivo.']);

        $this->post(route('batches.water-quality.store', $this->batch), $this->reading(['water_quality_metric_id' => 999999]))
            ->assertSessionHasErrors('water_quality_metric_id');

        $this->assertSame(0, WaterQualityReading::count());
    }

    public function test_reading_must_be_within_the_metric_physical_range(): void
    {
        $this->actingAs($this->admin)
            ->post(route('batches.water-quality.store', $this->batch), $this->reading(['water_quality_value' => '-1']))
            ->assertSessionHasErrors(['water_quality_value' => 'El valor está por debajo del rango físico válido de pH (mínimo 0,00).']);

        $this->post(route('batches.water-quality.store', $this->batch), $this->reading(['water_quality_value' => '15']))
            ->assertSessionHasErrors(['water_quality_value' => 'El valor está por encima del rango físico válido de pH (máximo 14,00).']);

        $this->post(route('batches.water-quality.store', $this->batch), $this->reading(['water_quality_value' => '0']))->assertSessionHasNoErrors();
        $this->post(route('batches.water-quality.store', $this->batch), $this->reading(['water_quality_value' => '14']))->assertSessionHasNoErrors();

        // Un parámetro sin rango físico configurado acepta cualquier valor numérico.
        $unbounded = Metric::factory()->create(['min_value' => null, 'max_value' => null]);
        $this->post(route('batches.water-quality.store', $this->batch), $this->reading(['water_quality_metric_id' => $unbounded->id, 'water_quality_value' => '99999']))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, WaterQualityReading::count());
    }

    public function test_dates_must_be_between_stocking_and_now(): void
    {
        $this->actingAs($this->admin);
        $stocked = today()->subDays(30)->format('d/m/Y');

        $this->post(route('batches.water-quality.store', $this->batch), $this->reading([
            'water_quality_date' => today()->subDays(31)->format('Y-m-d').'T00:00',
        ]))->assertSessionHasErrors(['water_quality_date' => "La fecha no puede ser anterior a la siembra del lote ({$stocked})."]);

        $this->post(route('batches.water-quality.store', $this->batch), $this->reading([
            'water_quality_date' => now()->addHour()->format('Y-m-d\TH:i'),
        ]))->assertSessionHasErrors(['water_quality_date' => 'La fecha no puede ser posterior al momento actual.']);

        $this->post(route('batches.water-quality.store', $this->batch), $this->reading(['water_quality_date' => '2026-13-40T99:99']))
            ->assertSessionHasErrors(['water_quality_date' => 'La fecha y hora no son válidas.']);

        $this->post(route('batches.water-quality.store', $this->batch), $this->reading(['water_quality_date' => '']))
            ->assertSessionHasErrors(['water_quality_date' => 'El campo fecha y hora es obligatorio.']);

        // El momento exacto de la siembra y el momento actual son válidos.
        $this->post(route('batches.water-quality.store', $this->batch), $this->reading(['water_quality_date' => today()->subDays(30)->format('Y-m-d').'T00:00']))
            ->assertSessionHasNoErrors();
        $this->post(route('batches.water-quality.store', $this->batch), $this->reading(['water_quality_date' => now()->format('Y-m-d\TH:i')]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, WaterQualityReading::count());
    }

    public function test_invalid_data_is_rejected_with_spanish_messages(): void
    {
        $this->actingAs($this->admin)
            ->post(route('batches.water-quality.store', $this->batch), [])
            ->assertSessionHasErrors([
                'water_quality_date' => 'El campo fecha y hora es obligatorio.',
                'water_quality_metric_id' => 'El campo parámetro es obligatorio.',
                'water_quality_value' => 'El campo valor es obligatorio.',
            ]);

        $this->post(route('batches.water-quality.store', $this->batch), $this->reading(['water_quality_value' => 'mucho']))
            ->assertSessionHasErrors(['water_quality_value' => 'El campo valor debe ser un número.']);

        $this->assertSame(0, WaterQualityReading::count());
    }

    public function test_closed_and_cancelled_batches_do_not_accept_readings(): void
    {
        $this->actingAs($this->admin);

        foreach ([Batch::factory()->closed()->create(), Batch::factory()->cancelled()->create()] as $batch) {
            $this->post(route('batches.water-quality.store', $batch), $this->reading([
                'water_quality_date' => today()->subDays(2)->format('Y-m-d').'T08:00',
            ]))->assertForbidden();

            $this->get(route('batches.show', $batch))
                ->assertOk()
                ->assertSee('ni admite nuevos registros')
                ->assertDontSee('Registrar medición');
        }

        $this->assertSame(0, WaterQualityReading::count());
    }

    public function test_history_can_be_filtered_by_parameter_and_date_and_is_paginated(): void
    {
        $oxygen = Metric::factory()->create(['code' => 'dissolved_oxygen', 'name' => 'Oxígeno disuelto']);

        WaterQualityReading::factory()->for($this->batch)->for($this->metric, 'metric')->create([
            'measured_at' => today()->subDays(10)->format('Y-m-d').' 08:00:00', 'notes' => 'Lectura pH vieja',
        ]);
        WaterQualityReading::factory()->for($this->batch)->for($oxygen, 'metric')->create([
            'measured_at' => today()->subDays(2)->format('Y-m-d').' 08:00:00', 'notes' => 'Lectura oxígeno',
        ]);

        $this->actingAs($this->admin);

        $this->get(route('batches.show', [$this->batch, 'wq_metric' => $this->metric->id]))
            ->assertSee('Lectura pH vieja')->assertDontSee('Lectura oxígeno');
        $this->get(route('batches.show', [$this->batch, 'wq_from' => today()->subDays(5)->toDateString()]))
            ->assertSee('Lectura oxígeno')->assertDontSee('Lectura pH vieja');
        $this->get(route('batches.show', [$this->batch, 'wq_to' => today()->subDays(5)->toDateString()]))
            ->assertSee('Lectura pH vieja')->assertDontSee('Lectura oxígeno');

        foreach (range(1, 13) as $i) {
            WaterQualityReading::factory()->for($this->batch)->for($this->metric, 'metric')->create([
                'measured_at' => today()->subHours($i)->format('Y-m-d H:i:s'), 'notes' => sprintf('Muestra %02d', $i),
            ]);
        }

        $this->get(route('batches.show', $this->batch))->assertSee('Muestra 01')->assertDontSee('Muestra 11');
        $this->get(route('batches.show', [$this->batch, 'calidad' => 2]))->assertSee('Muestra 13');
    }

    public function test_batch_detail_does_not_run_n_plus_one_queries_for_readings(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('batches.show', $this->batch))->assertOk();

            return count(DB::getQueryLog());
        };

        $this->actingAs($this->admin);
        WaterQualityReading::factory()->count(2)->for($this->batch)->for($this->metric, 'metric')->create(['user_id' => User::factory()]);
        $few = $count();

        WaterQualityReading::factory()->count(8)->for($this->batch)->for(Metric::factory())->create(['user_id' => User::factory()]);

        $this->assertSame($few, $count());
    }

    public function test_readings_cannot_be_deleted_and_protect_their_batch_and_metric(): void
    {
        WaterQualityReading::factory()->for($this->batch)->for($this->metric, 'metric')->create();

        $this->actingAs($this->admin)->delete(route('batches.water-quality.store', $this->batch))->assertStatus(405);

        $this->expectException(QueryException::class);
        $this->batch->delete();
    }
}
