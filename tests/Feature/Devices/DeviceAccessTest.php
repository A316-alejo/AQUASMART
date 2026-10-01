<?php

namespace Tests\Feature\Devices;

use App\Enums\DeviceType;
use App\Enums\Role;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Producer;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeviceAccessTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private Farm $assignedFarm;

    private Farm $siblingFarm;

    private Device $assignedDevice;

    private Device $siblingDevice;

    private Device $foreignDevice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->assignedFarm = Farm::factory()->for($this->producer)->create();
        $this->siblingFarm = Farm::factory()->for($this->producer)->create();

        $this->assignedDevice = Device::factory()->for($this->assignedFarm)->create(['code' => 'ASIG-1']);
        $this->siblingDevice = Device::factory()->for($this->siblingFarm)->create(['code' => 'HERM-1']);
        $this->foreignDevice = Device::factory()->create(['code' => 'AJENO-1']);
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
     * @return list<array{0: string, 1: string}>
     */
    private function adminRoutes(Device $device): array
    {
        return [
            ['get', route('devices.create')],
            ['post', route('devices.store')],
            ['get', route('devices.edit', $device)],
            ['put', route('devices.update', $device)],
            ['get', route('devices.deactivate.confirm', $device)],
            ['patch', route('devices.deactivate', $device)],
            ['patch', route('devices.activate', $device)],
            ['patch', route('devices.token.regenerate', $device)],
            ['get', route('devices.sensors.create', $device)],
            ['post', route('devices.sensors.store', $device)],
        ];
    }

    private function userFor(Role $role): User
    {
        $user = User::factory()->role($role)->forProducer($this->producer)->create();

        if ($role !== Role::Producer) {
            $this->assignedFarm->users()->attach($user);
        }

        return $user;
    }

    public function test_guests_cannot_access_devices(): void
    {
        $routes = [
            ['get', route('devices.index')],
            ['get', route('devices.show', $this->assignedDevice)],
            ...$this->adminRoutes($this->assignedDevice),
        ];

        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_run_administrative_actions(Role $role): void
    {
        $user = $this->userFor($role);

        foreach ($this->adminRoutes($this->assignedDevice) as [$method, $url]) {
            $this->actingAs($user)->{$method}($url, [
                'farm_id' => $this->assignedFarm->id, 'code' => 'HACK', 'name' => 'Cambio',
                'type' => DeviceType::Esp32->value, 'is_active' => '1',
            ])->assertForbidden();
        }

        $this->assertDatabaseMissing('devices', ['code' => 'HACK']);
        $this->assertTrue($this->assignedDevice->fresh()->is_active);
    }

    public function test_producer_sees_devices_of_all_their_farms_only(): void
    {
        $this->actingAs($this->userFor(Role::Producer))
            ->get(route('devices.index'))
            ->assertOk()
            ->assertSee('ASIG-1')
            ->assertSee('HERM-1')
            ->assertDontSee('AJENO-1')
            ->assertDontSee('Nuevo dispositivo');

        $this->get(route('devices.show', $this->siblingDevice))->assertOk()->assertDontSee(route('devices.edit', $this->siblingDevice));
        $this->get(route('devices.show', $this->foreignDevice))->assertForbidden();
    }

    #[DataProvider('assignableRoles')]
    public function test_technicians_and_viewers_see_only_devices_of_assigned_farms(Role $role): void
    {
        $this->actingAs($this->userFor($role))
            ->get(route('devices.index'))
            ->assertOk()
            ->assertSee('ASIG-1')
            ->assertDontSee('HERM-1')
            ->assertDontSee('AJENO-1');

        $this->get(route('devices.show', $this->assignedDevice))->assertOk();
        $this->get(route('devices.show', $this->siblingDevice))->assertForbidden();
        $this->get(route('devices.show', $this->foreignDevice))->assertForbidden();
    }

    public function test_access_follows_farm_assignment_changes(): void
    {
        $technician = $this->userFor(Role::Technician);

        $this->actingAs($technician)->get(route('devices.show', $this->assignedDevice))->assertOk();

        $this->assignedFarm->users()->detach($technician);

        $this->get(route('devices.show', $this->assignedDevice))->assertForbidden();
    }

    public function test_a_producer_without_producer_cannot_see_devices(): void
    {
        $this->actingAs(User::factory()->role(Role::Producer)->create());

        $this->get(route('devices.index'))->assertForbidden();
        $this->get(route('devices.show', $this->assignedDevice))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee(route('devices.index'));
    }

    public function test_menu_shows_devices_to_users_with_access(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get(route('dashboard'))->assertSee(route('devices.index'));

        $this->actingAs($this->userFor(Role::Technician))
            ->get(route('dashboard'))->assertSee(route('devices.index'));
    }

    public function test_non_numeric_ids_are_not_found(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())->get('/dispositivos/abc')->assertNotFound();
    }

    public function test_token_is_never_exposed_in_listing_or_edit_forms(): void
    {
        $device = Device::factory()->for($this->assignedFarm)->create();
        $token = $device->generateToken();

        $this->actingAs($this->userFor(Role::Producer));

        $this->get(route('devices.index'))->assertDontSee($token)->assertDontSee($device->token_hash);
        $this->get(route('devices.show', $device))->assertDontSee($token)->assertDontSee($device->token_hash);
    }

    public function test_sensor_access_follows_its_devices_visibility(): void
    {
        $sensor = Sensor::factory()->for($this->assignedDevice)->create();
        $foreignSensor = Sensor::factory()->for($this->foreignDevice)->create();

        $technician = $this->userFor(Role::Technician);

        $this->assertTrue($sensor->isVisibleTo($technician));
        $this->assertFalse($foreignSensor->isVisibleTo($technician));
    }
}
