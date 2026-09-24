<?php

namespace Tests\Feature\Farms;

use App\Enums\Role;
use App\Models\Farm;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FarmManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Producer $producer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->producer = Producer::factory()->create(['name' => 'Piscícola El Paraíso']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'producer_id' => $this->producer->id,
            'name' => 'Finca La Esperanza',
            'location' => 'Vereda El Juncal',
            'municipality' => 'Palermo',
            'department' => 'Huila',
            'latitude' => '2,8876543',
            'longitude' => '-75.4312345',
            'area_ha' => '12,5',
            'timezone' => 'America/Bogota',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_admin_can_list_farms_with_totals(): void
    {
        $farms = Farm::factory()->count(2)->for($this->producer)->create(['area_ha' => 10]);
        Farm::factory()->inactive()->create(['area_ha' => 5]);

        $response = $this->actingAs($this->admin)->get(route('farms.index'))->assertOk();

        foreach ($farms as $farm) {
            $response->assertSee($farm->name);
        }

        $response->assertSee('Piscícola El Paraíso')
            ->assertSee('Fincas registradas')
            ->assertSee('25,0')
            ->assertSee('Nueva finca');
    }

    public function test_admin_can_create_a_farm(): void
    {
        $this->actingAs($this->admin)->get(route('farms.create', ['producer' => $this->producer->id]))
            ->assertOk()
            ->assertSee('Piscícola El Paraíso');

        $this->post(route('farms.store'), $this->payload(['name' => '  Finca   La Esperanza ']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $farm = Farm::where('name', 'Finca La Esperanza')->firstOrFail();

        $this->assertTrue($farm->producer->is($this->producer));
        $this->assertSame('2.8876543', $farm->latitude);
        $this->assertSame('-75.4312345', $farm->longitude);
        $this->assertSame('12.50', $farm->area_ha);
        $this->assertSame('America/Bogota', $farm->timezone);
        $this->assertTrue($farm->is_active);
    }

    public function test_a_producer_can_have_many_farms_and_each_farm_one_producer(): void
    {
        Farm::factory()->count(3)->for($this->producer)->create();
        $otherFarm = Farm::factory()->create();

        $this->assertSame(3, $this->producer->farms()->count());
        $this->assertFalse($otherFarm->producer->is($this->producer));
        $this->assertTrue($this->producer->farms()->with('producer')->first()->producer->is($this->producer));
    }

    public function test_admin_can_view_a_farm(): void
    {
        $farm = Farm::factory()->for($this->producer)->create(['latitude' => 2.5, 'longitude' => -75.5]);

        $this->actingAs($this->admin)
            ->get(route('farms.show', $farm))
            ->assertOk()
            ->assertSee($farm->name)
            ->assertSee('Piscícola El Paraíso')
            ->assertSee('Ver en el mapa')
            ->assertSee('Personal asignado');
    }

    public function test_admin_can_edit_a_farm_but_not_change_its_producer(): void
    {
        $farm = Farm::factory()->for($this->producer)->create();
        $other = Producer::factory()->create();

        $this->actingAs($this->admin)->get(route('farms.edit', $farm))->assertOk();

        $payload = $this->payload(['name' => 'Finca Renombrada', 'latitude' => '', 'longitude' => '', 'area_ha' => '']);
        unset($payload['producer_id'], $payload['is_active']);

        $this->put(route('farms.update', $farm), $payload)
            ->assertRedirect(route('farms.show', $farm))
            ->assertSessionHasNoErrors();

        $farm->refresh();
        $this->assertSame('Finca Renombrada', $farm->name);
        $this->assertNull($farm->latitude);
        $this->assertNull($farm->area_ha);

        $this->put(route('farms.update', $farm), [...$payload, 'producer_id' => $other->id])
            ->assertSessionHasErrors('producer_id');
        $this->assertTrue($farm->fresh()->producer->is($this->producer));
    }

    public function test_admin_can_deactivate_and_activate_a_farm(): void
    {
        $farm = Farm::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('farms.deactivate.confirm', $farm))
            ->assertOk()
            ->assertSee('¿Desactivar '.$farm->name.'?', false);

        $this->patch(route('farms.deactivate', $farm))->assertRedirect(route('farms.show', $farm));
        $this->assertFalse($farm->fresh()->is_active);
        $this->get(route('farms.show', $farm))->assertSee('Esta finca está inactiva');
        $this->patch(route('farms.deactivate', $farm))->assertForbidden();

        $this->patch(route('farms.activate', $farm))->assertRedirect(route('farms.show', $farm));
        $this->assertTrue($farm->fresh()->is_active);
        $this->patch(route('farms.activate', $farm))->assertForbidden();
    }

    public function test_search_and_filters(): void
    {
        $meta = Producer::factory()->create();
        Farm::factory()->for($this->producer)->create(['name' => 'Finca El Recreo', 'location' => 'Vereda Buenos Aires', 'municipality' => 'Palermo', 'department' => 'Huila']);
        Farm::factory()->for($meta)->inactive()->create(['name' => 'Hacienda Las Palmas', 'location' => 'Km 12', 'municipality' => 'Villavicencio', 'department' => 'Meta']);

        $this->actingAs($this->admin);

        $this->get(route('farms.index', ['q' => 'recreo']))->assertSee('Finca El Recreo')->assertDontSee('Hacienda Las Palmas');
        $this->get(route('farms.index', ['q' => 'buenos aires']))->assertSee('Finca El Recreo')->assertDontSee('Hacienda Las Palmas');
        $this->get(route('farms.index', ['q' => 'villavicencio']))->assertSee('Hacienda Las Palmas')->assertDontSee('Finca El Recreo');
        $this->get(route('farms.index', ['producer' => $meta->id]))->assertSee('Hacienda Las Palmas')->assertDontSee('Finca El Recreo');
        $this->get(route('farms.index', ['department' => 'Huila']))->assertSee('Finca El Recreo')->assertDontSee('Hacienda Las Palmas');
        $this->get(route('farms.index', ['status' => 'inactive']))->assertSee('Hacienda Las Palmas')->assertDontSee('Finca El Recreo');
        $this->get(route('farms.index', ['q' => 'no-existe']))->assertSee('No hay fincas que coincidan');
    }

    public function test_farms_are_paginated(): void
    {
        foreach (range(1, 17) as $i) {
            Farm::factory()->for($this->producer)->create(['name' => sprintf('Finca %02d', $i)]);
        }

        $this->actingAs($this->admin);
        $this->get(route('farms.index'))->assertSee('Finca 15')->assertDontSee('Finca 16')->assertSee('Mostrando 1–15 de 17');
        $this->get(route('farms.index', ['page' => 2]))->assertSee('Finca 17')->assertDontSee('Finca 01');
    }

    public function test_listing_does_not_run_n_plus_one_queries(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('farms.index'))->assertOk();

            return count(DB::getQueryLog());
        };

        $this->actingAs($this->admin);
        Farm::factory()->count(2)->create();
        $few = $count();

        Farm::factory()->count(10)->create()->each(fn (Farm $farm) => $farm->users()->attach(User::factory()->role(Role::Technician)->create()));
        $many = $count();

        $this->assertSame($few, $many);
    }

    public function test_farm_names_are_unique_per_producer(): void
    {
        Farm::factory()->for($this->producer)->create(['name' => 'Finca La Esperanza']);

        $this->actingAs($this->admin)
            ->post(route('farms.store'), $this->payload())
            ->assertSessionHasErrors(['name' => 'Este productor ya tiene una finca con ese nombre.']);

        $this->post(route('farms.store'), $this->payload(['producer_id' => Producer::factory()->create()->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Farm::where('name', 'Finca La Esperanza')->count());
    }

    public function test_farms_cannot_be_created_for_inactive_producers(): void
    {
        $inactive = Producer::factory()->inactive()->create();

        $this->actingAs($this->admin)
            ->post(route('farms.store'), $this->payload(['producer_id' => $inactive->id]))
            ->assertSessionHasErrors(['producer_id' => 'El productor seleccionado no existe o está inactivo.']);

        $this->assertSame(0, Farm::count());
    }

    public function test_invalid_data_is_rejected_with_spanish_messages(): void
    {
        $this->actingAs($this->admin)
            ->post(route('farms.store'), [])
            ->assertSessionHasErrors([
                'producer_id' => 'El campo productor es obligatorio.',
                'name' => 'El campo nombre de la finca es obligatorio.',
                'municipality' => 'El campo municipio es obligatorio.',
                'department' => 'El campo departamento es obligatorio.',
                'timezone' => 'El campo zona horaria es obligatorio.',
                'is_active' => 'El campo estado es obligatorio.',
            ]);

        $this->post(route('farms.store'), $this->payload([
            'latitude' => '95',
            'longitude' => '',
            'area_ha' => '-3',
            'timezone' => 'Marte/Olympus',
            'department' => 'Texas',
        ]))->assertSessionHasErrors([
            'latitude' => 'El campo latitud debe estar entre -90 y 90.',
            'longitude' => 'El campo longitud es obligatorio cuando latitud está presente.',
            'area_ha' => 'El campo área debe ser mayor que 0.',
            'timezone' => 'El campo zona horaria debe ser una zona horaria válida.',
            'department' => 'El valor seleccionado en departamento no es válido.',
        ]);

        $this->assertSame(0, Farm::count());
    }

    public function test_farms_cannot_be_deleted(): void
    {
        $farm = Farm::factory()->for($this->producer)->create();

        $this->actingAs($this->admin)->delete('/fincas/'.$farm->id)->assertStatus(405);
        $this->assertDatabaseHas('farms', ['id' => $farm->id]);

        $this->expectException(QueryException::class);
        $this->producer->delete();
    }

    public function test_producer_detail_lists_its_farms(): void
    {
        Farm::factory()->for($this->producer)->create(['name' => 'Finca Uno']);
        Farm::factory()->for($this->producer)->inactive()->create(['name' => 'Finca Dos']);
        Farm::factory()->create(['name' => 'Finca Ajena']);

        $this->actingAs($this->admin)
            ->get(route('producers.show', $this->producer))
            ->assertSee('Finca Uno')
            ->assertSee('Finca Dos')
            ->assertSee('Inactiva')
            ->assertDontSee('Finca Ajena')
            ->assertSee(route('farms.create', ['producer' => $this->producer->id]), false);
    }
}
