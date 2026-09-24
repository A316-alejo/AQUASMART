<?php

namespace Tests\Feature\Ponds;

use App\Enums\PondType;
use App\Enums\Role;
use App\Models\Farm;
use App\Models\Pond;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PondManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->farm = Farm::factory()->create(['name' => 'Finca La Esperanza']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'farm_id' => $this->farm->id,
            'code' => 'E01',
            'name' => 'Estanque de engorde 1',
            'type' => PondType::Geomembrane->value,
            'surface_m2' => '800,5',
            'volume_m3' => '1200',
            'depth_m' => '1,5',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_admin_can_list_ponds_with_totals(): void
    {
        Pond::factory()->for($this->farm)->create(['code' => 'E01', 'volume_m3' => 1000]);
        Pond::factory()->for($this->farm)->inactive()->create(['code' => 'E02', 'volume_m3' => 500]);

        $this->actingAs($this->admin)
            ->get(route('ponds.index'))
            ->assertOk()
            ->assertSee('E01')
            ->assertSee('E02')
            ->assertSee('Finca La Esperanza')
            ->assertSee('Estanques registrados')
            ->assertSee('1.500')
            ->assertSee('Nuevo estanque');
    }

    public function test_admin_can_create_a_pond(): void
    {
        $this->actingAs($this->admin)
            ->get(route('ponds.create', ['farm' => $this->farm->id]))
            ->assertOk()
            ->assertSee('Finca La Esperanza');

        $this->post(route('ponds.store'), $this->payload(['code' => ' e-01 ']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $pond = Pond::where('code', 'E-01')->firstOrFail();

        $this->assertTrue($pond->farm->is($this->farm));
        $this->assertSame(PondType::Geomembrane, $pond->type);
        $this->assertSame('800.50', $pond->surface_m2);
        $this->assertSame('1200.00', $pond->volume_m3);
        $this->assertSame('1.50', $pond->depth_m);
        $this->assertTrue($pond->is_active);
    }

    public function test_every_pond_type_can_be_stored(): void
    {
        $this->actingAs($this->admin);

        foreach (PondType::cases() as $i => $type) {
            $this->post(route('ponds.store'), $this->payload(['code' => 'T'.$i, 'type' => $type->value]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(count(PondType::cases()), $this->farm->ponds()->count());
    }

    public function test_a_farm_can_have_many_ponds_and_each_pond_one_farm(): void
    {
        Pond::factory()->count(3)->for($this->farm)->create();
        $other = Pond::factory()->create();

        $this->assertSame(3, $this->farm->ponds()->count());
        $this->assertFalse($other->farm->is($this->farm));
        $this->assertTrue($this->farm->ponds()->with('farm')->first()->farm->is($this->farm));
    }

    public function test_admin_can_view_a_pond_and_the_farm_lists_its_ponds(): void
    {
        $pond = Pond::factory()->for($this->farm)->create(['code' => 'E07', 'name' => 'Reproductores']);

        $this->actingAs($this->admin)
            ->get(route('ponds.show', $pond))
            ->assertOk()
            ->assertSee('E07 · Reproductores')
            ->assertSee('Finca La Esperanza')
            ->assertSee($this->farm->producer->name);

        $this->get(route('farms.show', $this->farm))
            ->assertSee('E07 · Reproductores')
            ->assertSee(route('ponds.create', ['farm' => $this->farm->id]), false);
    }

    public function test_admin_can_edit_a_pond_but_not_move_it_to_another_farm(): void
    {
        $pond = Pond::factory()->for($this->farm)->create(['code' => 'E01']);
        $otherFarm = Farm::factory()->create();

        $this->actingAs($this->admin)->get(route('ponds.edit', $pond))->assertOk();

        $payload = $this->payload(['name' => 'Estanque renombrado', 'type' => PondType::Ras->value, 'surface_m2' => '', 'depth_m' => '']);
        unset($payload['farm_id'], $payload['is_active']);

        $this->put(route('ponds.update', $pond), $payload)
            ->assertRedirect(route('ponds.show', $pond))
            ->assertSessionHasNoErrors();

        $pond->refresh();
        $this->assertSame('Estanque renombrado', $pond->name);
        $this->assertSame(PondType::Ras, $pond->type);
        $this->assertNull($pond->surface_m2);

        $this->put(route('ponds.update', $pond), [...$payload, 'farm_id' => $otherFarm->id])
            ->assertSessionHasErrors('farm_id');
        $this->assertTrue($pond->fresh()->farm->is($this->farm));
    }

    public function test_admin_can_deactivate_and_activate_a_pond(): void
    {
        $pond = Pond::factory()->for($this->farm)->create(['code' => 'E05']);

        $this->actingAs($this->admin)
            ->get(route('ponds.deactivate.confirm', $pond))
            ->assertOk()
            ->assertSee('¿Desactivar el estanque E05?');

        $this->patch(route('ponds.deactivate', $pond))->assertRedirect(route('ponds.show', $pond));
        $this->assertFalse($pond->fresh()->is_active);
        $this->patch(route('ponds.deactivate', $pond))->assertForbidden();

        $this->patch(route('ponds.activate', $pond))->assertRedirect(route('ponds.show', $pond));
        $this->assertTrue($pond->fresh()->is_active);
        $this->patch(route('ponds.activate', $pond))->assertForbidden();
    }

    public function test_ponds_cannot_be_created_or_reactivated_in_inactive_farms(): void
    {
        $inactiveFarm = Farm::factory()->inactive()->create();
        $pond = Pond::factory()->for($inactiveFarm)->inactive()->create();

        $this->actingAs($this->admin)
            ->post(route('ponds.store'), $this->payload(['farm_id' => $inactiveFarm->id]))
            ->assertSessionHasErrors(['farm_id' => 'La finca seleccionada no existe o está inactiva.']);

        $this->get(route('ponds.create'))->assertDontSee($inactiveFarm->name);

        $this->patch(route('ponds.activate', $pond))->assertForbidden();
        $this->get(route('ponds.show', $pond))->assertSee('está inactiva');
        $this->assertFalse($pond->fresh()->is_active);
        $this->assertSame(1, Pond::count());
    }

    public function test_code_is_unique_within_each_farm(): void
    {
        Pond::factory()->for($this->farm)->create(['code' => 'E01']);
        $second = Pond::factory()->for($this->farm)->create(['code' => 'E02']);

        $this->actingAs($this->admin)
            ->post(route('ponds.store'), $this->payload(['code' => 'e01']))
            ->assertSessionHasErrors(['code' => 'Esta finca ya tiene un estanque con ese código.']);

        $this->post(route('ponds.store'), $this->payload(['farm_id' => Farm::factory()->create()->id]))
            ->assertSessionHasNoErrors();

        $payload = $this->payload(['code' => 'E01']);
        unset($payload['farm_id'], $payload['is_active']);
        $this->put(route('ponds.update', $second), $payload)->assertSessionHasErrors('code');

        $payload['code'] = 'E02';
        $this->put(route('ponds.update', $second), $payload)->assertSessionHasNoErrors();

        $this->assertSame(2, Pond::where('code', 'E01')->count());
    }

    public function test_search_and_filters(): void
    {
        $otherFarm = Farm::factory()->create(['name' => 'Hacienda Las Palmas']);
        Pond::factory()->for($this->farm)->create(['code' => 'E01', 'name' => 'Alevinaje', 'type' => PondType::Tank]);
        Pond::factory()->for($otherFarm)->inactive()->create(['code' => 'R09', 'name' => 'Engorde norte', 'type' => PondType::Earthen]);

        $this->actingAs($this->admin);

        $this->get(route('ponds.index', ['q' => 'alevin']))->assertSee('E01')->assertDontSee('R09');
        $this->get(route('ponds.index', ['q' => 'r09']))->assertSee('R09')->assertDontSee('Alevinaje');
        $this->get(route('ponds.index', ['farm' => $otherFarm->id]))->assertSee('R09')->assertDontSee('Alevinaje');
        $this->get(route('ponds.index', ['type' => PondType::Tank->value]))->assertSee('E01')->assertDontSee('R09');
        $this->get(route('ponds.index', ['status' => 'inactive']))->assertSee('R09')->assertDontSee('Alevinaje');
        $this->get(route('ponds.index', ['q' => 'no-existe']))->assertSee('No hay estanques que coincidan');
    }

    public function test_ponds_are_paginated(): void
    {
        foreach (range(1, 17) as $i) {
            Pond::factory()->for($this->farm)->create(['code' => sprintf('P%02d', $i)]);
        }

        $this->actingAs($this->admin);
        $this->get(route('ponds.index'))->assertSee('P15')->assertDontSee('P16')->assertSee('Mostrando 1–15 de 17');
        $this->get(route('ponds.index', ['page' => 2]))->assertSee('P17')->assertDontSee('P01');
    }

    public function test_listing_does_not_run_n_plus_one_queries(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('ponds.index'))->assertOk();

            return count(DB::getQueryLog());
        };

        $this->actingAs($this->admin);
        Pond::factory()->count(2)->create();
        $few = $count();

        Pond::factory()->count(10)->create();
        $many = $count();

        $this->assertSame($few, $many);
    }

    public function test_invalid_data_is_rejected_with_spanish_messages(): void
    {
        $this->actingAs($this->admin)
            ->post(route('ponds.store'), [])
            ->assertSessionHasErrors([
                'farm_id' => 'El campo finca es obligatorio.',
                'code' => 'El campo código es obligatorio.',
                'name' => 'El campo nombre del estanque es obligatorio.',
                'type' => 'El campo tipo de estanque es obligatorio.',
                'surface_m2' => 'Registra al menos la superficie o el volumen del estanque.',
                'is_active' => 'El campo estado es obligatorio.',
            ]);

        $this->post(route('ponds.store'), $this->payload([
            'code' => 'E 01/A',
            'type' => 'piscina',
            'surface_m2' => '-5',
            'volume_m3' => 'mucho',
            'depth_m' => '80',
        ]))->assertSessionHasErrors([
            'code' => 'El código solo puede tener letras, números y guiones, sin espacios.',
            'type' => 'El valor seleccionado en tipo de estanque no es válido.',
            'surface_m2' => 'El campo superficie debe ser mayor que 0.',
            'volume_m3' => 'El campo volumen debe ser un número.',
            'depth_m' => 'El campo profundidad no debe ser mayor que 50.',
        ]);

        $this->assertSame(0, Pond::count());
    }

    public function test_ponds_cannot_be_deleted(): void
    {
        $pond = Pond::factory()->for($this->farm)->create();

        $this->actingAs($this->admin)->delete('/estanques/'.$pond->id)->assertStatus(405);
        $this->assertDatabaseHas('ponds', ['id' => $pond->id]);

        $this->expectException(QueryException::class);
        $this->farm->delete();
    }
}
