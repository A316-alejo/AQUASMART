<?php

namespace Tests\Feature\Farms;

use App\Enums\Role;
use App\Models\Farm;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FarmAccessTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private Farm $ownFarm;

    private Farm $otherFarm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->ownFarm = Farm::factory()->for($this->producer)->create(['name' => 'Finca Propia']);
        $this->otherFarm = Farm::factory()->create(['name' => 'Finca Ajena']);
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'productor' => [Role::Producer],
            'técnico' => [Role::Technician],
            'consulta' => [Role::Viewer],
        ];
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function assignableRoles(): array
    {
        return [
            'técnico' => [Role::Technician],
            'consulta' => [Role::Viewer],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function adminRoutes(Farm $farm, User $member): array
    {
        return [
            'formulario de creación' => ['get', route('farms.create')],
            'crear' => ['post', route('farms.store')],
            'formulario de edición' => ['get', route('farms.edit', $farm)],
            'editar' => ['put', route('farms.update', $farm)],
            'confirmar desactivación' => ['get', route('farms.deactivate.confirm', $farm)],
            'desactivar' => ['patch', route('farms.deactivate', $farm)],
            'activar' => ['patch', route('farms.activate', $farm)],
            'asignar' => ['post', route('farms.assignments.store', $farm)],
            'quitar asignación' => ['delete', route('farms.assignments.destroy', [$farm, $member])],
        ];
    }

    public function test_guests_cannot_access_farms(): void
    {
        $member = User::factory()->create();
        $routes = [
            ['get', route('farms.index')],
            ['get', route('farms.show', $this->ownFarm)],
            ...array_values($this->adminRoutes($this->ownFarm, $member)),
        ];

        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_run_administrative_actions(Role $role): void
    {
        $user = User::factory()->role($role)->forProducer($this->producer)->create();
        $this->ownFarm->users()->attach($user);
        $member = User::factory()->role(Role::Technician)->create();

        foreach ($this->adminRoutes($this->ownFarm, $member) as [$method, $url]) {
            $this->actingAs($user)->{$method}($url, [
                'producer_id' => $this->producer->id, 'name' => 'Cambio', 'municipality' => 'Neiva',
                'department' => 'Huila', 'timezone' => 'America/Bogota', 'is_active' => '1', 'user_id' => $member->id,
            ])->assertForbidden();
        }

        $this->assertDatabaseMissing('farms', ['name' => 'Cambio']);
        $this->assertTrue($this->ownFarm->fresh()->is_active);
        $this->assertFalse($this->ownFarm->users()->whereKey($member->id)->exists());
    }

    public function test_producer_sees_only_the_farms_of_their_producer(): void
    {
        $user = User::factory()->role(Role::Producer)->forProducer($this->producer)->create();
        Farm::factory()->for($this->producer)->inactive()->create(['name' => 'Finca Propia Inactiva']);

        $this->actingAs($user)
            ->get(route('farms.index'))
            ->assertOk()
            ->assertSee('Finca Propia')
            ->assertSee('Finca Propia Inactiva')
            ->assertDontSee('Finca Ajena')
            ->assertDontSee('Nueva finca');

        $this->get(route('farms.index', ['q' => 'Ajena']))->assertDontSee('Finca Ajena');
        $this->get(route('farms.index', ['producer' => $this->otherFarm->producer_id]))->assertDontSee('Finca Ajena');

        $this->get(route('farms.show', $this->ownFarm))->assertOk()->assertDontSee(route('farms.edit', $this->ownFarm));
        $this->get(route('farms.show', $this->otherFarm))->assertForbidden();
    }

    public function test_producer_without_a_producer_cannot_see_farms(): void
    {
        $this->actingAs(User::factory()->role(Role::Producer)->create());

        $this->get(route('farms.index'))->assertForbidden();
        $this->get(route('farms.show', $this->ownFarm))->assertForbidden();
    }

    #[DataProvider('assignableRoles')]
    public function test_technicians_and_viewers_only_see_assigned_farms(Role $role): void
    {
        $user = User::factory()->role($role)->create();
        $sameProducerFarm = Farm::factory()->for($this->producer)->create(['name' => 'Finca Hermana']);
        $this->ownFarm->users()->attach($user);

        $this->actingAs($user)
            ->get(route('farms.index'))
            ->assertOk()
            ->assertSee('Finca Propia')
            ->assertDontSee('Finca Hermana')
            ->assertDontSee('Finca Ajena');

        $this->get(route('farms.show', $this->ownFarm))->assertOk()->assertSee($user->name);
        $this->get(route('farms.show', $sameProducerFarm))->assertForbidden();
        $this->get(route('farms.show', $this->otherFarm))->assertForbidden();
    }

    #[DataProvider('assignableRoles')]
    public function test_unassigned_technicians_and_viewers_see_no_farms(Role $role): void
    {
        $this->actingAs(User::factory()->role($role)->forProducer($this->producer)->create())
            ->get(route('farms.index'))
            ->assertOk()
            ->assertSee('No tienes fincas disponibles')
            ->assertDontSee('Finca Propia');

        $this->get(route('farms.show', $this->ownFarm))->assertForbidden();
    }

    public function test_assignments_to_another_producers_farm_grant_no_access(): void
    {
        $user = User::factory()->role(Role::Technician)->forProducer($this->producer)->create();
        $this->otherFarm->users()->attach($user);

        $this->actingAs($user)->get(route('farms.index'))->assertDontSee('Finca Ajena');
        $this->get(route('farms.show', $this->otherFarm))->assertForbidden();
    }

    public function test_menu_shows_farms_according_to_role(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get(route('dashboard'))->assertSee(route('farms.index'));

        $this->actingAs(User::factory()->role(Role::Technician)->create())
            ->get(route('dashboard'))->assertSee(route('farms.index'));

        $this->actingAs(User::factory()->role(Role::Producer)->create())
            ->get(route('dashboard'))->assertDontSee(route('farms.index'));
    }

    public function test_non_numeric_ids_are_not_found(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())->get('/fincas/abc')->assertNotFound();
    }

    public function test_admin_can_assign_and_remove_technicians(): void
    {
        $admin = User::factory()->role(Role::Admin)->create();
        $technician = User::factory()->role(Role::Technician)->create(['name' => 'Tomás Técnico']);
        $viewer = User::factory()->role(Role::Viewer)->forProducer($this->producer)->create(['name' => 'Clara Consulta']);

        $this->actingAs($admin)->get(route('farms.show', $this->ownFarm))
            ->assertSee('Tomás Técnico')->assertSee('Clara Consulta');

        $this->post(route('farms.assignments.store', $this->ownFarm), ['user_id' => $technician->id])
            ->assertRedirect(route('farms.show', $this->ownFarm))
            ->assertSessionHasNoErrors();
        $this->post(route('farms.assignments.store', $this->ownFarm), ['user_id' => $viewer->id])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$technician->id, $viewer->id], $this->ownFarm->users()->pluck('users.id')->all());
        $this->assertTrue($technician->farms->first()->is($this->ownFarm));

        $second = Farm::factory()->for($this->producer)->create();
        $this->post(route('farms.assignments.store', $second), ['user_id' => $technician->id])->assertSessionHasNoErrors();
        $this->assertSame(2, $technician->farms()->count());

        $this->delete(route('farms.assignments.destroy', [$this->ownFarm, $technician]))
            ->assertRedirect(route('farms.show', $this->ownFarm));
        $this->assertFalse($this->ownFarm->users()->whereKey($technician->id)->exists());
        $this->assertDatabaseHas('users', ['id' => $technician->id]);

        $this->delete(route('farms.assignments.destroy', [$this->ownFarm, $technician]))->assertNotFound();
    }

    public function test_assignment_rules_are_enforced(): void
    {
        $admin = User::factory()->role(Role::Admin)->create();
        $url = route('farms.assignments.store', $this->ownFarm);
        $assigned = User::factory()->role(Role::Technician)->create();
        $this->ownFarm->users()->attach($assigned);

        $cases = [
            [User::factory()->role(Role::Producer)->forProducer($this->producer)->create(), 'Solo se pueden asignar usuarios con rol Técnico o Consulta; los productores ya ven las fincas de su productor.'],
            [User::factory()->role(Role::Admin)->create(), 'Solo se pueden asignar usuarios con rol Técnico o Consulta; los productores ya ven las fincas de su productor.'],
            [User::factory()->role(Role::Technician)->inactive()->create(), 'La cuenta seleccionada está inactiva.'],
            [User::factory()->role(Role::Technician)->forProducer($this->otherFarm->producer)->create(), 'La cuenta pertenece a otro productor y no puede asignarse a esta finca.'],
            [$assigned, 'La cuenta ya está asignada a esta finca.'],
        ];

        foreach ($cases as [$user, $message]) {
            $this->actingAs($admin)->post($url, ['user_id' => $user->id])->assertSessionHasErrors(['user_id' => $message]);
        }

        $this->post($url, ['user_id' => 999999])->assertSessionHasErrors(['user_id' => 'El valor seleccionado en usuario no es válido.']);
        $this->post($url, [])->assertSessionHasErrors(['user_id' => 'El campo usuario es obligatorio.']);

        $this->assertSame(1, $this->ownFarm->users()->count());
    }

    public function test_deleting_a_user_account_removes_their_assignments(): void
    {
        $technician = User::factory()->role(Role::Technician)->create();
        $this->ownFarm->users()->attach($technician);

        $technician->delete();

        $this->assertDatabaseMissing('farm_user', ['user_id' => $technician->id]);
        $this->assertDatabaseHas('farms', ['id' => $this->ownFarm->id]);
    }
}
