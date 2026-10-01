<?php

namespace Tests\Feature\Devices;

use App\Enums\DeviceType;
use App\Enums\Role;
use App\Models\Device;
use App\Models\Farm;
use App\Models\Metric;
use App\Models\Pond;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceManagementTest extends TestCase
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
            'code' => 'DEV-01',
            'name' => 'Gateway estanque 1',
            'type' => DeviceType::Esp32->value,
            'is_active' => '1',
            ...$overrides,
        ];
    }

    public function test_admin_can_create_a_device_and_see_its_token_only_once(): void
    {
        $this->actingAs($this->admin)
            ->get(route('devices.create', ['farm' => $this->farm->id]))
            ->assertOk()
            ->assertSee('Finca La Esperanza');

        $response = $this->post(route('devices.store'), $this->payload(['code' => ' dev-01 ']));
        $response->assertSessionHasNoErrors()->assertSessionHas('status')->assertSessionHas('device_token');

        $device = Device::where('code', 'DEV-01')->firstOrFail();
        $this->assertTrue($device->farm->is($this->farm));
        $this->assertSame(DeviceType::Esp32, $device->type);
        $this->assertTrue($device->is_active);

        $token = session('device_token');
        $this->assertSame(hash('sha256', $token), $device->token_hash);

        $this->followingRedirects()->get(route('devices.show', $device))->assertSee($token);
        // El token es de un solo uso: al volver a cargar la página (nueva petición, sin flash) ya no aparece.
        $this->get(route('devices.show', $device))->assertDontSee($token);
    }

    public function test_token_is_never_stored_in_plain_text(): void
    {
        $device = Device::factory()->for($this->farm)->create();
        $token = $device->generateToken();

        $this->assertDatabaseMissing('devices', ['token_hash' => $token]);
        $this->assertSame(hash('sha256', $token), $device->fresh()->token_hash);
    }

    public function test_admin_can_regenerate_a_devices_token(): void
    {
        $device = Device::factory()->for($this->farm)->create();
        $oldToken = $device->generateToken();

        $this->actingAs($this->admin)
            ->patch(route('devices.token.regenerate', $device))
            ->assertRedirect(route('devices.show', $device))
            ->assertSessionHas('device_token');

        $newToken = session('device_token');
        $this->assertNotSame($oldToken, $newToken);
        $this->assertNull(Device::findByToken($oldToken));
        $this->assertTrue($device->is(Device::findByToken($newToken)));
    }

    public function test_device_code_is_unique(): void
    {
        Device::factory()->for($this->farm)->create(['code' => 'DEV-01']);

        $this->actingAs($this->admin)
            ->post(route('devices.store'), $this->payload(['code' => 'dev-01']))
            ->assertSessionHasErrors(['code' => 'Ya existe un dispositivo con ese código.']);

        $this->assertSame(1, Device::where('code', 'DEV-01')->count());
    }

    public function test_admin_can_edit_a_device_but_not_move_it_to_another_farm(): void
    {
        $device = Device::factory()->for($this->farm)->create(['code' => 'DEV-01']);
        $otherFarm = Farm::factory()->create();

        $this->actingAs($this->admin)->get(route('devices.edit', $device))->assertOk();

        $payload = $this->payload(['name' => 'Gateway renombrado', 'type' => DeviceType::Gateway->value]);
        unset($payload['farm_id'], $payload['is_active']);

        $this->put(route('devices.update', $device), $payload)
            ->assertRedirect(route('devices.show', $device))
            ->assertSessionHasNoErrors();

        $device->refresh();
        $this->assertSame('Gateway renombrado', $device->name);
        $this->assertSame(DeviceType::Gateway, $device->type);

        $this->put(route('devices.update', $device), [...$payload, 'farm_id' => $otherFarm->id])
            ->assertSessionHasErrors('farm_id');
        $this->assertTrue($device->fresh()->farm->is($this->farm));
    }

    public function test_admin_can_deactivate_and_activate_a_device(): void
    {
        $device = Device::factory()->for($this->farm)->create(['code' => 'DEV-05']);

        $this->actingAs($this->admin)
            ->get(route('devices.deactivate.confirm', $device))
            ->assertOk()
            ->assertSee('¿Desactivar el dispositivo DEV-05?');

        $this->patch(route('devices.deactivate', $device))->assertRedirect(route('devices.show', $device));
        $this->assertFalse($device->fresh()->is_active);
        $this->patch(route('devices.deactivate', $device))->assertForbidden();

        $this->patch(route('devices.activate', $device))->assertRedirect(route('devices.show', $device));
        $this->assertTrue($device->fresh()->is_active);
        $this->patch(route('devices.activate', $device))->assertForbidden();
    }

    public function test_invalid_device_data_is_rejected_with_spanish_messages(): void
    {
        $this->actingAs($this->admin)
            ->post(route('devices.store'), [])
            ->assertSessionHasErrors([
                'farm_id' => 'El campo finca es obligatorio.',
                'code' => 'El campo código es obligatorio.',
                'name' => 'El campo nombre del dispositivo es obligatorio.',
                'type' => 'El campo tipo de dispositivo es obligatorio.',
                'is_active' => 'El campo estado es obligatorio.',
            ]);

        $this->post(route('devices.store'), $this->payload(['code' => 'DEV 01/A', 'type' => 'arduino']))
            ->assertSessionHasErrors([
                'code' => 'El código solo puede tener letras, números y guiones, sin espacios.',
                'type' => 'El valor seleccionado en tipo de dispositivo no es válido.',
            ]);

        $this->assertSame(0, Device::count());
    }

    public function test_devices_cannot_be_deleted(): void
    {
        $device = Device::factory()->for($this->farm)->create();

        $this->actingAs($this->admin)->delete('/dispositivos/'.$device->id)->assertStatus(405);
        $this->assertDatabaseHas('devices', ['id' => $device->id]);
    }

    public function test_search_and_filters(): void
    {
        $otherFarm = Farm::factory()->create(['name' => 'Hacienda Las Palmas']);
        Device::factory()->for($this->farm)->create(['code' => 'DEV-01', 'name' => 'Gateway norte', 'type' => DeviceType::Esp32]);
        Device::factory()->for($otherFarm)->inactive()->create(['code' => 'GW-09', 'name' => 'Gateway sur', 'type' => DeviceType::Gateway]);

        $this->actingAs($this->admin);

        $this->get(route('devices.index', ['q' => 'gateway norte']))->assertSee('DEV-01')->assertDontSee('GW-09');
        $this->get(route('devices.index', ['farm' => $otherFarm->id]))->assertSee('GW-09')->assertDontSee('DEV-01');
        $this->get(route('devices.index', ['type' => DeviceType::Gateway->value]))->assertSee('GW-09')->assertDontSee('DEV-01');
        $this->get(route('devices.index', ['status' => 'inactive']))->assertSee('GW-09')->assertDontSee('DEV-01');
        $this->get(route('devices.index', ['q' => 'no-existe']))->assertSee('No hay dispositivos que coincidan');
    }

    public function test_listing_does_not_run_n_plus_one_queries(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('devices.index'))->assertOk();

            return count(DB::getQueryLog());
        };

        $this->actingAs($this->admin);
        Device::factory()->count(2)->create();
        $few = $count();

        Device::factory()->count(10)->create();
        $many = $count();

        $this->assertSame($few, $many);
    }

    public function test_admin_can_create_a_sensor_and_the_pond_must_belong_to_the_devices_farm(): void
    {
        $device = Device::factory()->for($this->farm)->create();
        $pond = Pond::factory()->for($this->farm)->create(['code' => 'E01']);
        $metric = Metric::factory()->create(['name' => 'pH']);
        $foreignPond = Pond::factory()->create(['code' => 'AJENO-1']);

        $this->actingAs($this->admin)
            ->get(route('devices.sensors.create', $device))
            ->assertOk()
            ->assertSee('E01')
            ->assertDontSee('AJENO-1');

        $this->post(route('devices.sensors.store', $device), [
            'pond_id' => $foreignPond->id, 'metric_id' => $metric->id, 'code' => 'S01', 'name' => 'Sonda pH', 'is_active' => '1',
        ])->assertSessionHasErrors(['pond_id' => 'El estanque seleccionado no existe, está inactivo o no pertenece a la finca del dispositivo.']);

        $this->post(route('devices.sensors.store', $device), [
            'pond_id' => $pond->id, 'metric_id' => $metric->id, 'code' => ' s01 ', 'name' => 'Sonda pH', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $sensor = Sensor::where('code', 'S01')->firstOrFail();
        $this->assertTrue($sensor->device->is($device));
        $this->assertTrue($sensor->pond->is($pond));
        $this->assertTrue($sensor->metric->is($metric));
    }

    public function test_sensor_code_is_unique_within_device_but_not_globally(): void
    {
        $device = Device::factory()->for($this->farm)->create();
        $otherDevice = Device::factory()->for($this->farm)->create();
        $pond = Pond::factory()->for($this->farm)->create();
        $metric = Metric::factory()->create();

        Sensor::factory()->for($device)->for($pond)->for($metric, 'metric')->create(['code' => 'S01']);

        $this->actingAs($this->admin)
            ->post(route('devices.sensors.store', $device), [
                'pond_id' => $pond->id, 'metric_id' => $metric->id, 'code' => 's01', 'name' => 'Duplicado', 'is_active' => '1',
            ])->assertSessionHasErrors(['code' => 'Este dispositivo ya tiene un sensor con ese código.']);

        $this->post(route('devices.sensors.store', $otherDevice), [
            'pond_id' => $pond->id, 'metric_id' => $metric->id, 'code' => 'S01', 'name' => 'Mismo código, otro dispositivo', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Sensor::where('code', 'S01')->count());
    }

    public function test_admin_can_edit_deactivate_and_activate_a_sensor(): void
    {
        $device = Device::factory()->for($this->farm)->create();
        $pond = Pond::factory()->for($this->farm)->create();
        $metric = Metric::factory()->create();
        $sensor = Sensor::factory()->for($device)->for($pond)->for($metric, 'metric')->create(['code' => 'S01']);

        $this->actingAs($this->admin)
            ->put(route('devices.sensors.update', [$device, $sensor]), [
                'pond_id' => $pond->id, 'metric_id' => $metric->id, 'code' => 'S01', 'name' => 'Sonda renombrada',
            ])
            ->assertRedirect(route('devices.show', $device))
            ->assertSessionHasNoErrors();

        $this->assertSame('Sonda renombrada', $sensor->fresh()->name);

        $this->patch(route('devices.sensors.deactivate', [$device, $sensor]))->assertRedirect(route('devices.show', $device));
        $this->assertFalse($sensor->fresh()->is_active);

        $this->patch(route('devices.sensors.activate', [$device, $sensor]))->assertRedirect(route('devices.show', $device));
        $this->assertTrue($sensor->fresh()->is_active);
    }

    public function test_sensor_routes_require_the_sensor_to_belong_to_the_device_in_the_url(): void
    {
        $device = Device::factory()->for($this->farm)->create();
        $otherDevice = Device::factory()->for($this->farm)->create();
        $sensor = Sensor::factory()->for($otherDevice)->create();

        $this->actingAs($this->admin)->get(route('devices.sensors.edit', [$device, $sensor]))->assertNotFound();
    }
}
