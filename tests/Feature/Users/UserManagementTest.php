<?php

namespace Tests\Feature\Users;

use App\Enums\Role;
use App\Models\Producer;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->role(Role::Admin)->create(['name' => 'Ana Admin']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function protectedRoutes(User $target): array
    {
        return [
            'listado' => ['get', route('users.index')],
            'formulario de creación' => ['get', route('users.create')],
            'crear' => ['post', route('users.store')],
            'formulario de edición' => ['get', route('users.edit', $target)],
            'editar' => ['put', route('users.update', $target)],
            'confirmar desactivación' => ['get', route('users.deactivate.confirm', $target)],
            'desactivar' => ['patch', route('users.deactivate', $target)],
            'activar' => ['patch', route('users.activate', $target)],
            'enlace de contraseña' => ['post', route('users.password-link', $target)],
        ];
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

    public function test_admin_can_access_user_management(): void
    {
        $admin = $this->admin();
        $other = User::factory()->role(Role::Technician)->create(['name' => 'Tomás Técnico']);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Usuarios')
            ->assertSee($other->name)
            ->assertSee($other->email)
            ->assertSee('Técnico');

        $this->get(route('users.create'))->assertOk();
        $this->get(route('users.edit', $other))->assertOk();
        $this->get(route('users.deactivate.confirm', $other))->assertOk()->assertSee('¿Desactivar a Tomás Técnico?');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_cannot_manage_users(Role $role): void
    {
        $actor = User::factory()->role($role)->create();
        $target = User::factory()->role(Role::Viewer)->create();

        foreach ($this->protectedRoutes($target) as $name => [$method, $url]) {
            $this->actingAs($actor)
                ->{$method}($url, [
                    'name' => 'Intruso',
                    'email' => 'intruso@example.com',
                    'role' => Role::Admin->value,
                    'is_active' => '1',
                    'password' => 'Clave12345',
                    'password_confirmation' => 'Clave12345',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
        $this->assertSame(Role::Viewer, $target->fresh()->role);
        $this->assertTrue($target->fresh()->is_active);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_do_not_see_the_users_menu(Role $role): void
    {
        $this->actingAs(User::factory()->role($role)->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('users.index'));
    }

    public function test_admin_sees_the_users_menu(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertSee(route('users.index'));
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_cannot_promote_themselves(Role $role): void
    {
        $actor = User::factory()->role($role)->create();

        $this->actingAs($actor)
            ->put(route('users.update', $actor), [
                'name' => $actor->name,
                'email' => $actor->email,
                'role' => Role::Admin->value,
            ])
            ->assertForbidden();

        $this->assertSame($role, $actor->fresh()->role);
    }

    public function test_guests_cannot_access_user_management(): void
    {
        $target = User::factory()->create();

        foreach ($this->protectedRoutes($target) as $name => [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
    }

    public function test_inactive_admin_cannot_manage_users(): void
    {
        $admin = User::factory()->role(Role::Admin)->inactive()->create();

        $this->assertTrue(Gate::forUser($admin)->denies('manage-users'));
        $this->actingAs($admin)->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_create_a_user(): void
    {
        $producer = Producer::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => 'Pedro Productor',
                'email' => '  Pedro@Finca.CO ',
                'role' => Role::Producer->value,
                'producer_id' => $producer->id,
                'is_active' => '1',
                'password' => 'Tilapia2026',
                'password_confirmation' => 'Tilapia2026',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $user = User::where('email', 'pedro@finca.co')->firstOrFail();

        $this->assertSame('Pedro Productor', $user->name);
        $this->assertSame(Role::Producer, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->producer->is($producer));
    }

    public function test_admin_can_create_an_inactive_user(): void
    {
        $this->actingAs($this->admin())->post(route('users.store'), [
            'name' => 'Cuenta Pendiente',
            'email' => 'pendiente@example.com',
            'role' => Role::Viewer->value,
            'is_active' => '0',
            'password' => 'Clave12345',
            'password_confirmation' => 'Clave12345',
        ])->assertSessionHasNoErrors();

        $this->assertFalse(User::where('email', 'pendiente@example.com')->firstOrFail()->is_active);
    }

    public function test_passwords_are_hashed_and_never_stored_in_plain_text(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Clara Consulta',
            'email' => 'clara@example.com',
            'role' => Role::Viewer->value,
            'is_active' => '1',
            'password' => 'Secreta2026',
            'password_confirmation' => 'Secreta2026',
        ]);

        $user = User::where('email', 'clara@example.com')->firstOrFail();
        $stored = DB::table('users')->where('id', $user->id)->value('password');

        $this->assertNotSame('Secreta2026', $stored);
        $this->assertTrue(Hash::isHashed($stored));
        $this->assertTrue(Hash::check('Secreta2026', $stored));

        $this->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'Distinta2026',
            'password_confirmation' => 'Distinta2026',
        ])->assertSessionHasNoErrors();

        $stored = DB::table('users')->where('id', $user->id)->value('password');
        $this->assertNotSame('Distinta2026', $stored);
        $this->assertTrue(Hash::check('Distinta2026', $stored));
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $user = User::factory()->role(Role::Viewer)->create();

        $this->actingAs($this->admin())
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => Role::Technician->value,
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(Role::Technician, $user->fresh()->role);
    }

    public function test_invalid_roles_are_rejected(): void
    {
        $admin = $this->admin();
        $user = User::factory()->role(Role::Viewer)->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Rol Inventado',
                'email' => 'inventado@example.com',
                'role' => 'superadmin',
                'is_active' => '1',
                'password' => 'Clave12345',
                'password_confirmation' => 'Clave12345',
            ])
            ->assertSessionHasErrors(['role' => 'El valor seleccionado en rol no es válido.']);

        $this->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'superadmin',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'inventado@example.com']);
        $this->assertSame(Role::Viewer, $user->fresh()->role);
    }

    public function test_validation_messages_are_in_spanish(): void
    {
        $existing = User::factory()->create(['email' => 'repetido@example.com']);

        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => '',
                'email' => 'repetido@example.com',
                'role' => '',
                'is_active' => 'quizas',
                'password' => 'corta',
                'password_confirmation' => 'otra',
            ])
            ->assertSessionHasErrors([
                'name' => 'El campo nombre es obligatorio.',
                'email' => 'El valor de correo electrónico ya está registrado.',
                'role' => 'El campo rol es obligatorio.',
                'is_active' => 'El campo estado debe ser verdadero o falso.',
                'password' => 'La confirmación del campo contraseña no coincide.',
            ]);

        $this->post(route('users.store'), ['email' => 'no-es-un-correo'])
            ->assertSessionHasErrors(['email' => 'El campo correo electrónico debe ser un correo electrónico válido.']);

        $this->assertSame(1, User::where('email', $existing->email)->count());
    }

    public function test_admin_cannot_change_their_own_role_or_deactivate_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => Role::Viewer->value,
            ])
            ->assertSessionHasErrors(['role' => 'No puedes cambiar tu propio rol.']);

        $this->get(route('users.deactivate.confirm', $admin))->assertForbidden();
        $this->patch(route('users.deactivate', $admin))->assertForbidden();

        $admin->refresh();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
    }

    public function test_admin_can_update_their_own_profile_without_touching_the_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('users.update', $admin), [
                'name' => 'Ana María Admin',
                'email' => $admin->email,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Ana María Admin', $admin->fresh()->name);
        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_changing_your_own_password_requires_the_current_one(): void
    {
        $admin = $this->admin();
        $payload = [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ];

        $this->actingAs($admin)
            ->put(route('users.update', $admin), $payload)
            ->assertSessionHasErrors('current_password');

        $this->put(route('users.update', $admin), [...$payload, 'current_password' => 'incorrecta'])
            ->assertSessionHasErrors(['current_password' => 'La contraseña es incorrecta.']);

        $this->put(route('users.update', $admin), [...$payload, 'current_password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NuevaClave2026', $admin->fresh()->password));
    }

    public function test_admin_can_deactivate_and_reactivate_a_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->role(Role::Technician)->create();

        $this->actingAs($admin)
            ->patch(route('users.deactivate', $user))
            ->assertRedirect(route('users.index'));
        $this->assertFalse($user->fresh()->is_active);

        $this->patch(route('users.deactivate', $user))->assertForbidden();

        $this->patch(route('users.activate', $user))->assertRedirect(route('users.index'));
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_deactivated_users_cannot_log_in(): void
    {
        $user = User::factory()->role(Role::Producer)->create();

        $this->actingAs($this->admin())->patch(route('users.deactivate', $user));
        $this->post(route('logout'));

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.inactive')]);

        $this->assertGuest();
    }

    public function test_deactivating_a_user_ends_their_open_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'sesion-abierta',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
        $rememberToken = $user->remember_token;

        $this->actingAs($this->admin())->patch(route('users.deactivate', $user));

        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-abierta']);
        $this->assertNotSame($rememberToken, $user->fresh()->remember_token);
    }

    public function test_admin_can_reset_another_users_password(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'sesion-vieja',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($this->admin())
            ->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'Temporal2026',
                'password_confirmation' => 'Temporal2026',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Temporal2026', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'sesion-vieja']);
    }

    public function test_admin_can_send_a_password_reset_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->from(route('users.edit', $user))
            ->post(route('users.password-link', $user))
            ->assertRedirect(route('users.edit', $user))
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_roles_can_only_assign_roles_up_to_their_own_level(): void
    {
        $admin = User::factory()->role(Role::Admin)->make();
        $producer = User::factory()->role(Role::Producer)->make();

        foreach (Role::cases() as $role) {
            $this->assertTrue(Gate::forUser($admin)->allows('assign-role', $role));
            $this->assertTrue(Gate::forUser($producer)->denies('assign-role', $role));
        }

        $this->assertSame([Role::Producer, Role::Technician, Role::Viewer], Role::assignableBy(Role::Producer));
        $this->assertSame(Role::cases(), Role::assignableBy(Role::Admin));
    }

    public function test_user_list_can_be_filtered(): void
    {
        User::factory()->role(Role::Producer)->create(['name' => 'Pedro Productor']);
        User::factory()->role(Role::Viewer)->inactive()->create(['name' => 'Iván Inactivo']);

        $this->actingAs($this->admin());

        $this->get(route('users.index', ['q' => 'Pedro']))
            ->assertSee('Pedro Productor')
            ->assertDontSee('Iván Inactivo');

        $this->get(route('users.index', ['status' => 'inactive']))
            ->assertSee('Iván Inactivo')
            ->assertDontSee('Pedro Productor');

        $this->get(route('users.index', ['role' => Role::Producer->value]))
            ->assertSee('Pedro Productor')
            ->assertDontSee('Iván Inactivo');
    }
}
