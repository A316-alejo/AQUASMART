<?php

namespace Tests\Feature\Batches;

use App\Enums\BatchStatus;
use App\Enums\Role;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Pond;
use App\Models\Species;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BatchManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Pond $pond;

    private Species $species;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->pond = Pond::factory()
            ->for(Farm::factory()->create(['name' => 'Finca La Esperanza']))
            ->create(['code' => 'E01', 'name' => 'Engorde 1']);
        $this->species = Species::factory()->create(['common_name' => 'Tilapia roja']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'pond_id' => $this->pond->id,
            'species_id' => $this->species->id,
            'code' => 'L-2026-001',
            'name' => 'Siembra de septiembre',
            'stocked_at' => today()->subDays(5)->toDateString(),
            'initial_quantity' => '12.000',
            'initial_average_weight_g' => '2,5',
            'notes' => 'Alevinos de proveedor certificado.',
            ...$overrides,
        ];
    }

    private function editablePayload(array $overrides = []): array
    {
        $payload = $this->payload($overrides);
        unset($payload['pond_id']);

        return $payload;
    }

    public function test_admin_can_list_batches_with_totals(): void
    {
        Batch::factory()->for($this->pond)->for($this->species)->create(['code' => 'L-A', 'initial_quantity' => 1000]);
        Batch::factory()->for($this->pond)->for($this->species)->create(['code' => 'L-B', 'initial_quantity' => 500]);
        Batch::factory()->for($this->pond)->for($this->species)->closed()->create(['code' => 'L-C']);
        Batch::factory()->for($this->pond)->for($this->species)->cancelled()->create(['code' => 'L-D']);

        $this->actingAs($this->admin)
            ->get(route('batches.index'))
            ->assertOk()
            ->assertSee(['L-A', 'L-B', 'L-C', 'L-D', 'Finca La Esperanza', 'Tilapia roja', 'Nuevo lote'])
            ->assertSee('1.500')
            ->assertSeeInOrder(['Lotes activos', '2', 'Peces sembrados', '1.500', 'Cerrados', '1', 'Cancelados', '1']);
    }

    public function test_admin_can_create_a_batch(): void
    {
        $this->actingAs($this->admin)
            ->get(route('batches.create', ['pond' => $this->pond->id]))
            ->assertOk()
            ->assertSee('Finca La Esperanza — E01 · Engorde 1')
            ->assertSee('Tilapia roja');

        $this->post(route('batches.store'), $this->payload(['code' => ' l-2026-001 ']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $batch = Batch::where('code', 'L-2026-001')->firstOrFail();

        $this->assertTrue($batch->pond->is($this->pond));
        $this->assertTrue($batch->species->is($this->species));
        $this->assertSame(BatchStatus::Active, $batch->status);
        $this->assertSame(12000, $batch->initial_quantity);
        $this->assertSame('2.50', $batch->initial_average_weight_g);
        $this->assertSame(today()->subDays(5)->toDateString(), $batch->stocked_at->toDateString());
        $this->assertNull($batch->closed_at);
    }

    public function test_status_and_closing_date_cannot_be_set_on_creation(): void
    {
        $this->actingAs($this->admin)
            ->post(route('batches.store'), $this->payload(['status' => 'cerrado', 'closed_at' => today()->toDateString()]))
            ->assertSessionHasNoErrors();

        $batch = Batch::sole();
        $this->assertSame(BatchStatus::Active, $batch->status);
        $this->assertNull($batch->closed_at);
    }

    public function test_relations_between_farm_pond_batch_and_species(): void
    {
        $old = Batch::factory()->for($this->pond)->for($this->species)->closed()->create();
        $current = Batch::factory()->for($this->pond)->for($this->species)->create();

        $this->assertSame([$old->id, $current->id], $this->pond->batches()->orderBy('id')->pluck('id')->all());
        $this->assertSame(2, $this->species->batches()->count());
        $this->assertSame(2, $this->pond->farm->batches()->count());
        $this->assertTrue($current->pond->farm->is($this->pond->farm));
    }

    public function test_admin_can_view_a_batch_and_the_pond_lists_its_history(): void
    {
        $batch = Batch::factory()->for($this->pond)->for($this->species)->create([
            'code' => 'L-07', 'name' => 'Reproductores', 'stocked_at' => today()->subDays(30)->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('batches.show', $batch))
            ->assertOk()
            ->assertSee('L-07 · Reproductores')
            ->assertSee('Finca La Esperanza')
            ->assertSee('Tilapia roja')
            ->assertSee('Días de cultivo')
            ->assertSee('30');

        $this->get(route('ponds.show', $this->pond))
            ->assertSee('L-07 · Reproductores')
            ->assertSee(route('batches.create', ['pond' => $this->pond->id]), false);
    }

    public function test_admin_can_edit_an_active_batch_but_not_move_it_to_another_pond(): void
    {
        $batch = Batch::factory()->for($this->pond)->for($this->species)->create(['code' => 'L-01']);
        $otherPond = Pond::factory()->create();
        $otherSpecies = Species::factory()->create();

        $this->actingAs($this->admin)->get(route('batches.edit', $batch))->assertOk();

        $this->put(route('batches.update', $batch), $this->editablePayload([
            'code' => 'L-01', 'name' => 'Lote renombrado', 'species_id' => $otherSpecies->id, 'initial_quantity' => '9000',
        ]))->assertRedirect(route('batches.show', $batch))->assertSessionHasNoErrors();

        $batch->refresh();
        $this->assertSame('Lote renombrado', $batch->name);
        $this->assertTrue($batch->species->is($otherSpecies));
        $this->assertSame(9000, $batch->initial_quantity);

        $this->put(route('batches.update', $batch), [...$this->editablePayload(['code' => 'L-01']), 'pond_id' => $otherPond->id])
            ->assertSessionHasErrors(['pond_id' => 'El estanque de un lote no se puede cambiar.']);
        $this->assertTrue($batch->fresh()->pond->is($this->pond));
    }

    public function test_editing_keeps_a_species_that_was_deactivated_after_stocking(): void
    {
        $batch = Batch::factory()->for($this->pond)->for($this->species)->create(['code' => 'L-01']);
        $this->species->update(['is_active' => false]);

        $this->actingAs($this->admin)
            ->put(route('batches.update', $batch), $this->editablePayload(['code' => 'L-01', 'name' => 'Otro nombre']))
            ->assertSessionHasNoErrors();

        $this->put(route('batches.update', $batch), $this->editablePayload([
            'code' => 'L-01', 'species_id' => Species::factory()->inactive()->create()->id,
        ]))->assertSessionHasErrors(['species_id' => 'La especie seleccionada no existe o está inactiva.']);
    }

    public function test_admin_can_close_a_batch_recording_the_closing_date(): void
    {
        $batch = Batch::factory()->for($this->pond)->for($this->species)->create([
            'code' => 'L-05', 'stocked_at' => today()->subDays(150)->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('batches.close.confirm', $batch))
            ->assertOk()
            ->assertSee('¿Cerrar el lote L-05?');

        $this->patch(route('batches.close', $batch), ['closed_at' => today()->subDays(151)->toDateString()])
            ->assertSessionHasErrors('closed_at');
        $this->patch(route('batches.close', $batch), ['closed_at' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors(['closed_at' => 'La fecha de cierre no puede ser posterior a hoy.']);
        $this->patch(route('batches.close', $batch), [])
            ->assertSessionHasErrors(['closed_at' => 'El campo fecha de cierre es obligatorio.']);
        $this->assertTrue($batch->fresh()->isActive());

        $this->patch(route('batches.close', $batch), ['closed_at' => today()->subDays(2)->toDateString()])
            ->assertRedirect(route('batches.show', $batch))
            ->assertSessionHas('status', 'El lote L-05 fue cerrado.');

        $batch->refresh();
        $this->assertSame(BatchStatus::Closed, $batch->status);
        $this->assertSame(today()->subDays(2)->toDateString(), $batch->closed_at->toDateString());
        $this->assertSame(148, $batch->daysInCulture());

        $this->get(route('batches.show', $batch))->assertSee('Este lote fue cerrado')->assertDontSee(route('batches.edit', $batch));
    }

    public function test_admin_can_cancel_a_batch(): void
    {
        $batch = Batch::factory()->for($this->pond)->for($this->species)->create(['code' => 'L-09']);

        $this->actingAs($this->admin)->get(route('batches.cancel.confirm', $batch))->assertOk();
        $this->patch(route('batches.cancel', $batch))->assertSessionHas('status', 'El lote L-09 fue cancelado.');

        $batch->refresh();
        $this->assertSame(BatchStatus::Cancelled, $batch->status);
        $this->assertSame(today()->toDateString(), $batch->closed_at->toDateString());
    }

    public function test_closed_and_cancelled_batches_cannot_be_edited_closed_or_cancelled_again(): void
    {
        $this->actingAs($this->admin);

        foreach ([Batch::factory()->closed(), Batch::factory()->cancelled()] as $factory) {
            $batch = $factory->for($this->pond)->for($this->species)->create();
            $closedAt = $batch->closed_at->toDateString();

            $this->get(route('batches.edit', $batch))->assertForbidden();
            $this->put(route('batches.update', $batch), $this->editablePayload(['code' => $batch->code]))->assertForbidden();
            $this->get(route('batches.close.confirm', $batch))->assertForbidden();
            $this->patch(route('batches.close', $batch), ['closed_at' => today()->toDateString()])->assertForbidden();
            $this->get(route('batches.cancel.confirm', $batch))->assertForbidden();
            $this->patch(route('batches.cancel', $batch))->assertForbidden();

            $this->assertSame($closedAt, $batch->fresh()->closed_at->toDateString());
        }
    }

    public function test_batches_cannot_be_created_in_inactive_ponds_or_farms(): void
    {
        $inactivePond = Pond::factory()->inactive()->create();
        $pondOfInactiveFarm = Pond::factory()->for(Farm::factory()->inactive())->create();

        $this->actingAs($this->admin);

        foreach ([$inactivePond, $pondOfInactiveFarm] as $pond) {
            $this->post(route('batches.store'), $this->payload(['pond_id' => $pond->id]))
                ->assertSessionHasErrors(['pond_id' => 'El estanque seleccionado no existe o está inactivo.']);
        }

        $this->get(route('batches.create'))
            ->assertDontSee($inactivePond->name)
            ->assertDontSee($pondOfInactiveFarm->name);

        $this->pond->update(['is_active' => false]);
        $this->get(route('ponds.show', $this->pond))->assertDontSee(route('batches.create', ['pond' => $this->pond->id]), false);

        $this->assertSame(0, Batch::count());
    }

    public function test_batches_cannot_be_created_with_inactive_species(): void
    {
        $inactive = Species::factory()->inactive()->create(['common_name' => 'Especie retirada']);

        $this->actingAs($this->admin)
            ->post(route('batches.store'), $this->payload(['species_id' => $inactive->id]))
            ->assertSessionHasErrors(['species_id' => 'La especie seleccionada no existe o está inactiva.']);

        $this->get(route('batches.create'))->assertDontSee('Especie retirada');
        $this->assertSame(0, Batch::count());
    }

    public function test_code_is_unique(): void
    {
        Batch::factory()->for($this->pond)->for($this->species)->create(['code' => 'L-01']);
        $second = Batch::factory()->for(Pond::factory())->for($this->species)->create(['code' => 'L-02']);

        $this->actingAs($this->admin)
            ->post(route('batches.store'), $this->payload(['code' => 'l-01', 'pond_id' => Pond::factory()->create()->id]))
            ->assertSessionHasErrors(['code' => 'Ya existe un lote con ese código.']);

        $this->put(route('batches.update', $second), $this->editablePayload(['code' => 'L-01']))->assertSessionHasErrors('code');
        $this->put(route('batches.update', $second), $this->editablePayload(['code' => 'L-02']))->assertSessionHasNoErrors();

        $this->assertSame(1, Batch::where('code', 'L-01')->count());
    }

    public function test_invalid_data_is_rejected_with_spanish_messages(): void
    {
        $this->actingAs($this->admin)
            ->post(route('batches.store'), [])
            ->assertSessionHasErrors([
                'pond_id' => 'El campo estanque es obligatorio.',
                'species_id' => 'El campo especie es obligatorio.',
                'code' => 'El campo código es obligatorio.',
                'name' => 'El campo nombre del lote es obligatorio.',
                'stocked_at' => 'El campo fecha de siembra es obligatorio.',
                'initial_quantity' => 'El campo cantidad sembrada es obligatorio.',
                'initial_average_weight_g' => 'El campo peso promedio inicial es obligatorio.',
            ]);

        $this->post(route('batches.store'), $this->payload([
            'code' => 'L 01/A',
            'stocked_at' => today()->addDay()->toDateString(),
            'initial_quantity' => '0',
            'initial_average_weight_g' => '-3',
        ]))->assertSessionHasErrors([
            'code' => 'El código solo puede tener letras, números y guiones, sin espacios.',
            'stocked_at' => 'La fecha de siembra no puede ser posterior a hoy.',
            'initial_quantity' => 'El campo cantidad sembrada debe ser al menos 1.',
            'initial_average_weight_g' => 'El campo peso promedio inicial debe ser mayor que 0.',
        ]);

        $this->post(route('batches.store'), $this->payload(['initial_quantity' => '10,5', 'stocked_at' => '31/12/2025']))
            ->assertSessionHasErrors([
                'initial_quantity' => 'El campo cantidad sembrada debe ser un número entero.',
                'stocked_at' => 'La fecha de siembra no es válida.',
            ]);

        $this->assertSame(0, Batch::count());
    }

    public function test_search_and_filters(): void
    {
        $otherFarm = Farm::factory()->create(['name' => 'Hacienda Las Palmas']);
        $cachama = Species::factory()->create(['common_name' => 'Cachama blanca']);
        Batch::factory()->for($this->pond)->for($this->species)->create(['code' => 'L-A1', 'name' => 'Alevinaje']);
        Batch::factory()->for(Pond::factory()->for($otherFarm))->for($cachama)->closed()->create(['code' => 'L-R9', 'name' => 'Engorde norte']);

        $this->actingAs($this->admin);

        $this->get(route('batches.index', ['q' => 'alevin']))->assertSee('L-A1')->assertDontSee('L-R9');
        $this->get(route('batches.index', ['q' => 'l-r9']))->assertSee('L-R9')->assertDontSee('Alevinaje');
        $this->get(route('batches.index', ['farm' => $otherFarm->id]))->assertSee('L-R9')->assertDontSee('Alevinaje');
        $this->get(route('batches.index', ['species' => $cachama->id]))->assertSee('L-R9')->assertDontSee('Alevinaje');
        $this->get(route('batches.index', ['status' => 'cerrado']))->assertSee('L-R9')->assertDontSee('Alevinaje');
        $this->get(route('batches.index', ['status' => 'otro']))->assertSessionHasErrors('status');
        $this->get(route('batches.index', ['q' => 'no-existe']))->assertSee('No hay lotes que coincidan');
    }

    public function test_batches_are_paginated(): void
    {
        foreach (range(1, 17) as $i) {
            Batch::factory()->for($this->pond)->for($this->species)->create([
                'code' => sprintf('P%02d', $i), 'stocked_at' => today()->subDays(100 - $i)->toDateString(),
            ]);
        }

        $this->actingAs($this->admin);
        $this->get(route('batches.index'))->assertSee('P17')->assertDontSee('P02')->assertSee('Mostrando 1–15 de 17');
        $this->get(route('batches.index', ['page' => 2]))->assertSee('P01')->assertDontSee('P17');
    }

    public function test_listing_and_pond_detail_do_not_run_n_plus_one_queries(): void
    {
        $count = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();

            return count(DB::getQueryLog());
        };

        $this->actingAs($this->admin);
        Batch::factory()->count(2)->for($this->pond)->create();
        $fewIndex = $count(route('batches.index'));
        $fewPond = $count(route('ponds.show', $this->pond));

        Batch::factory()->count(10)->for($this->pond)->create();
        Batch::factory()->count(5)->create();

        $this->assertSame($fewIndex, $count(route('batches.index')));
        $this->assertSame($fewPond, $count(route('ponds.show', $this->pond)));
    }

    public function test_batches_cannot_be_deleted(): void
    {
        $batch = Batch::factory()->for($this->pond)->for($this->species)->create();

        $this->actingAs($this->admin)->delete('/lotes/'.$batch->id)->assertStatus(405);
        $this->assertModelExists($batch);

        $this->expectException(QueryException::class);
        $this->pond->delete();
    }

    public function test_species_with_batches_cannot_be_deleted(): void
    {
        Batch::factory()->for($this->pond)->for($this->species)->create();

        $this->expectException(QueryException::class);
        $this->species->delete();
    }
}
