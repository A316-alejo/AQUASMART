<?php

namespace Tests\Feature\Producers;

use App\Enums\Role;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProducerAccessTest extends TestCase
{
    use RefreshDatabase;

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
     * @return array<string, array{0: string, 1: string}>
     */
    private function adminRoutes(Producer $producer): array
    {
        return [
            'formulario de creación' => ['get', route('producers.create')],
            'crear' => ['post', route('producers.store')],
            'formulario de edición' => ['get', route('producers.edit', $producer)],
            'editar' => ['put', route('producers.update', $producer)],
            'confirmar desactivación' => ['get', route('producers.deactivate.confirm', $producer)],
            'desactivar' => ['patch', route('producers.deactivate', $producer)],
            'activar' => ['patch', route('producers.activate', $producer)],
        ];
    }

    public function test_guests_cannot_access_producers(): void
    {
        $producer = Producer::factory()->create();

        $routes = [
            'listado' => ['get', route('producers.index')],
            'detalle' => ['get', route('producers.show', $producer)],
            ...$this->adminRoutes($producer),
        ];

        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_run_administrative_actions_even_on_their_own_producer(Role $role): void
    {
        $own = Producer::factory()->create();
        $user = User::factory()->role($role)->forProducer($own)->create();

        foreach ([$own, Producer::factory()->inactive()->create()] as $producer) {
            foreach ($this->adminRoutes($producer) as [$method, $url]) {
                $this->actingAs($user)->{$method}($url, [
                    'person_type' => 'natural', 'name' => 'Cambio', 'document_type' => 'CC',
                    'document_number' => '99999999', 'phone' => '3100000000', 'municipality' => 'Neiva',
                    'department' => 'Huila', 'is_active' => '1',
                ])->assertForbidden();
            }
        }

        $this->assertDatabaseMissing('producers', ['document_number' => '99999999']);
        $this->assertNotSame('Cambio', $own->fresh()->name);
        $this->assertTrue($own->fresh()->is_active);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_only_see_their_own_producer(Role $role): void
    {
        $own = Producer::factory()->create(['name' => 'Mi Productor']);
        $other = Producer::factory()->create(['name' => 'Productor Ajeno']);
        $user = User::factory()->role($role)->forProducer($own)->create();

        $this->actingAs($user)
            ->get(route('producers.index'))
            ->assertOk()
            ->assertSee('Mi Productor')
            ->assertDontSee('Productor Ajeno')
            ->assertDontSee('Nuevo productor')
            ->assertDontSee(route('producers.edit', $own));

        $this->get(route('producers.index', ['q' => 'Ajeno']))->assertDontSee('Productor Ajeno');

        $this->get(route('producers.show', $own))->assertOk()->assertSee('Mi Productor')->assertDontSee('Cuentas de usuario');

        $this->get(route('producers.show', $other))->assertForbidden();
    }

    #[DataProvider('nonAdminRoles')]
    public function test_users_without_a_producer_cannot_see_producers(Role $role): void
    {
        $producer = Producer::factory()->create();
        $user = User::factory()->role($role)->create();

        $this->actingAs($user);
        $this->get(route('producers.index'))->assertForbidden();
        $this->get(route('producers.show', $producer))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee(route('producers.index'));
    }

    public function test_menu_shows_producers_to_admin_and_linked_users(): void
    {
        $producer = Producer::factory()->create();

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get(route('dashboard'))->assertSee(route('producers.index'));

        $this->actingAs(User::factory()->role(Role::Producer)->forProducer($producer)->create())
            ->get(route('dashboard'))->assertSee(route('producers.index'));
    }

    public function test_non_numeric_ids_are_not_found(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get('/productores/abc')
            ->assertNotFound();
    }

    public function test_user_and_producer_relationships(): void
    {
        $producer = Producer::factory()->create();
        $first = User::factory()->role(Role::Producer)->forProducer($producer)->create();
        $second = User::factory()->role(Role::Technician)->forProducer($producer)->create();
        $unlinked = User::factory()->create();

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $producer->users->pluck('id')->all());
        $this->assertTrue($first->producer->is($producer));
        $this->assertNull($unlinked->producer);
        $this->assertSame(2, Producer::withCount('users')->find($producer->id)->users_count);
    }

    public function test_inactive_producers_are_identified(): void
    {
        $active = Producer::factory()->create();
        $inactive = Producer::factory()->inactive()->create();

        $this->assertEquals([$active->id], Producer::active()->pluck('id')->all());
        $this->assertFalse($inactive->is_active);

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get(route('producers.show', $inactive))
            ->assertSee('Este productor está inactivo')
            ->assertSee('Inactivo');
    }

    public function test_users_of_an_inactive_producer_cannot_log_in(): void
    {
        $producer = Producer::factory()->inactive()->create();
        $user = User::factory()->role(Role::Producer)->forProducer($producer)->create();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.producer_inactive')]);
        $this->assertGuest();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'incorrecta'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $producer->update(['is_active' => true]);
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_are_logged_out_when_their_producer_is_deactivated(): void
    {
        config(['session.driver' => 'database']);
        $producer = Producer::factory()->create();
        $user = User::factory()->role(Role::Technician)->forProducer($producer)->create();
        DB::table('sessions')->insert([
            'id' => 'sesion-del-tecnico',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->patch(route('producers.deactivate', $producer));

        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-del-tecnico']);

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => __('auth.producer_inactive')]);
        $this->assertGuest();
    }

    public function test_user_form_links_accounts_to_producers_with_rules(): void
    {
        $admin = User::factory()->role(Role::Admin)->create();
        $producer = Producer::factory()->create();
        $base = [
            'name' => 'Cuenta Nueva',
            'is_active' => '1',
            'password' => 'Clave12345',
            'password_confirmation' => 'Clave12345',
        ];

        $this->actingAs($admin)->get(route('users.create'))->assertSee('Productor asociado')->assertSee($producer->name);

        $this->post(route('users.store'), [...$base, 'email' => 'a@example.com', 'role' => Role::Producer->value])
            ->assertSessionHasErrors(['producer_id' => 'Un usuario con rol Productor debe estar asociado a un productor.']);

        $this->post(route('users.store'), [...$base, 'email' => 'b@example.com', 'role' => Role::Admin->value, 'producer_id' => $producer->id])
            ->assertSessionHasErrors(['producer_id' => 'Un administrador no puede estar asociado a un productor.']);

        $this->post(route('users.store'), [...$base, 'email' => 'c@example.com', 'role' => Role::Viewer->value, 'producer_id' => 999999])
            ->assertSessionHasErrors(['producer_id' => 'El valor seleccionado en productor asociado no es válido.']);

        $this->post(route('users.store'), [...$base, 'email' => 'd@example.com', 'role' => Role::Technician->value, 'producer_id' => $producer->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($producer->id, User::where('email', 'd@example.com')->value('producer_id'));
        $this->assertSame(0, User::whereIn('email', ['a@example.com', 'b@example.com', 'c@example.com'])->count());
    }
}
