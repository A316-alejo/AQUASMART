<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PondType;
use App\Enums\Role;
use App\Models\Farm;
use App\Models\Pond;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del CRUD REST de estanques (/api/v1/ponds). No reimplementa reglas: reutiliza los
 * mismos factories, Policies y Form Requests que ya cubre la suite web (PondAccessTest,
 * PondManagementTest); aquí solo se verifica que el mismo comportamiento se exponga en JSON
 * con los códigos HTTP correctos.
 */
class PondsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Producer $producer;

    private Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->producer = Producer::factory()->create();
        $this->farm = Farm::factory()->for($this->producer)->create();
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
            'surface_m2' => '800.5',
            'volume_m3' => '1200',
            'depth_m' => '1.5',
            'is_active' => '1',
            ...$overrides,
        ];
    }

    private function producerUser(): User
    {
        return User::factory()->role(Role::Producer)->forProducer($this->producer)->create();
    }

    // --- Acceso de invitados ---

    public function test_guests_cannot_access_the_api(): void
    {
        $pond = Pond::factory()->for($this->farm)->create();

        $routes = [
            ['get', '/api/v1/ponds'],
            ['get', "/api/v1/ponds/{$pond->id}"],
            ['post', '/api/v1/ponds'],
            ['put', "/api/v1/ponds/{$pond->id}"],
            ['patch', "/api/v1/ponds/{$pond->id}/activar"],
            ['patch', "/api/v1/ponds/{$pond->id}/desactivar"],
        ];

        foreach ($routes as [$method, $url]) {
            $this->json($method, $url)->assertStatus(401);
        }
    }

    // --- Listado ---

    public function test_index_lists_only_ponds_visible_to_the_user(): void
    {
        $siblingFarm = Farm::factory()->for($this->producer)->create();
        Pond::factory()->for($this->farm)->create(['code' => 'ASIG-1']);
        Pond::factory()->for($siblingFarm)->create(['code' => 'HERM-1']);
        Pond::factory()->create(['code' => 'AJENO-1']);

        $codes = collect(
            $this->actingAs($this->producerUser())->getJson('/api/v1/ponds')->assertOk()->json('data')
        )->pluck('code');

        $this->assertTrue($codes->contains('ASIG-1'));
        $this->assertTrue($codes->contains('HERM-1'));
        $this->assertFalse($codes->contains('AJENO-1'));
    }

    public function test_index_response_is_paginated(): void
    {
        Pond::factory()->count(17)->for($this->farm)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/ponds')->assertOk();

        $response->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(17, $response->json('meta.total'));
    }

    // --- Show ---

    public function test_show_returns_a_visible_pond(): void
    {
        $pond = Pond::factory()->for($this->farm)->create(['code' => 'E07']);

        $this->actingAs($this->admin)->getJson("/api/v1/ponds/{$pond->id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'E07')
            ->assertJsonPath('data.farm.id', $this->farm->id);
    }

    public function test_show_a_pond_of_another_producer_is_forbidden(): void
    {
        $foreignPond = Pond::factory()->create();

        $this->actingAs($this->producerUser())->getJson("/api/v1/ponds/{$foreignPond->id}")
            ->assertStatus(403);
    }

    public function test_show_a_nonexistent_pond_returns_404(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/ponds/999999')->assertStatus(404);
    }

    public function test_a_non_numeric_id_returns_404(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/ponds/abc')->assertStatus(404);
    }

    // --- Creación ---

    public function test_only_authorized_users_can_create_a_pond(): void
    {
        $this->actingAs($this->producerUser())->postJson('/api/v1/ponds', $this->payload())
            ->assertStatus(403);

        $this->assertSame(0, Pond::count());
    }

    public function test_store_creates_a_pond_with_valid_data(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/ponds', $this->payload(['code' => 'E-01']))
            ->assertCreated()
            ->assertJsonPath('data.code', 'E-01')
            ->assertJsonPath('data.farm.id', $this->farm->id);

        $this->assertDatabaseHas('ponds', ['code' => 'E-01', 'farm_id' => $this->farm->id]);
    }

    public function test_store_rejects_a_duplicate_code_within_the_same_farm(): void
    {
        Pond::factory()->for($this->farm)->create(['code' => 'E01']);

        $this->actingAs($this->admin)->postJson('/api/v1/ponds', $this->payload(['code' => 'E01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_store_rejects_an_inactive_farm(): void
    {
        $inactiveFarm = Farm::factory()->inactive()->create();

        $this->actingAs($this->admin)
            ->postJson('/api/v1/ponds', $this->payload(['farm_id' => $inactiveFarm->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('farm_id');

        $this->assertSame(0, Pond::count());
    }

    // --- Actualización ---

    public function test_update_modifies_a_pond_with_valid_data(): void
    {
        $pond = Pond::factory()->for($this->farm)->create(['code' => 'E01', 'name' => 'Viejo nombre']);

        $payload = $this->payload(['name' => 'Nuevo nombre']);
        unset($payload['farm_id'], $payload['is_active']);

        $this->actingAs($this->admin)->putJson("/api/v1/ponds/{$pond->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.name', 'Nuevo nombre');

        $this->assertSame('Nuevo nombre', $pond->fresh()->name);
    }

    public function test_update_cannot_change_the_farm(): void
    {
        $pond = Pond::factory()->for($this->farm)->create(['code' => 'E01']);
        $otherFarm = Farm::factory()->create();

        $payload = $this->payload(['farm_id' => $otherFarm->id]);
        unset($payload['is_active']);

        $this->actingAs($this->admin)->putJson("/api/v1/ponds/{$pond->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('farm_id');

        $this->assertTrue($pond->fresh()->farm->is($this->farm));
    }

    // --- Activar / desactivar ---

    public function test_activate_reactivates_an_inactive_pond(): void
    {
        $pond = Pond::factory()->for($this->farm)->inactive()->create();

        $this->actingAs($this->admin)->patchJson("/api/v1/ponds/{$pond->id}/activar")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertTrue($pond->fresh()->is_active);
    }

    public function test_activate_fails_if_the_farm_is_inactive(): void
    {
        $inactiveFarm = Farm::factory()->inactive()->create();
        $pond = Pond::factory()->for($inactiveFarm)->inactive()->create();

        $this->actingAs($this->admin)->patchJson("/api/v1/ponds/{$pond->id}/activar")
            ->assertStatus(403);

        $this->assertFalse($pond->fresh()->is_active);
    }

    public function test_deactivate_deactivates_an_active_pond(): void
    {
        $pond = Pond::factory()->for($this->farm)->create();

        $this->actingAs($this->admin)->patchJson("/api/v1/ponds/{$pond->id}/desactivar")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($pond->fresh()->is_active);
    }

    public function test_deactivate_an_already_inactive_pond_fails(): void
    {
        $pond = Pond::factory()->for($this->farm)->inactive()->create();

        $this->actingAs($this->admin)->patchJson("/api/v1/ponds/{$pond->id}/desactivar")
            ->assertStatus(403);
    }

    // --- Visibilidad por productor ---

    public function test_a_producer_cannot_access_ponds_of_another_producer(): void
    {
        $foreignPond = Pond::factory()->create();
        $producerUser = $this->producerUser();

        $this->actingAs($producerUser)->getJson("/api/v1/ponds/{$foreignPond->id}")
            ->assertStatus(403);

        $codes = collect(
            $this->actingAs($producerUser)->getJson('/api/v1/ponds')->assertOk()->json('data')
        )->pluck('code');

        $this->assertFalse($codes->contains($foreignPond->code));
    }
}
