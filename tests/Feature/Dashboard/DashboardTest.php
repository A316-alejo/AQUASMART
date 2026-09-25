<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AlertStatus;
use App\Enums\Role;
use App\Enums\WaterQualityStatus;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Batch;
use App\Models\Farm;
use App\Models\Feeding;
use App\Models\Metric;
use App\Models\Mortality;
use App\Models\Pond;
use App\Models\Producer;
use App\Models\Sampling;
use App\Models\User;
use App\Models\WaterQualityReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private Farm $farmA;

    private Farm $farmB;

    private Pond $pondA;

    private Batch $batch1;

    private Batch $batch3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->farmA = Farm::factory()->for($this->producer)->create();
        $this->farmB = Farm::factory()->for($this->producer)->create();

        $this->pondA = Pond::factory()->for($this->farmA)->create();
        $pondB = Pond::factory()->for($this->farmB)->create();

        // Lote activo con mortalidad y una biometría: prueba el cálculo real de biomasa.
        $this->batch1 = Batch::factory()->for($this->pondA)->create(['initial_quantity' => 1000, 'initial_average_weight_g' => 2]);
        Mortality::factory()->for($this->batch1)->create(['quantity' => 100]);
        Sampling::factory()->for($this->batch1)->create(['average_weight_g' => 50]);

        // Lote cerrado: no debe contarse como activo ni sumar a las cifras.
        Batch::factory()->for($this->pondA)->closed()->create(['initial_quantity' => 9999, 'initial_average_weight_g' => 9999]);

        // Segundo lote activo, sin registros productivos: usa el peso de siembra.
        $this->batch3 = Batch::factory()->for($pondB)->create(['initial_quantity' => 500, 'initial_average_weight_g' => 4]);
    }

    private function userFor(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->role($role)->forProducer($this->producer)->create();

        if ($farm && $role !== Role::Producer) {
            $farm->users()->attach($user);
        }

        return $user;
    }

    /**
     * Lee el valor exacto de una tarjeta KPI ("label" -> "value") o de una mini-métrica
     * ("m-lab" -> "m-val") por su etiqueta, en vez de buscar dígitos sueltos en toda la página
     * (que podrían coincidir con rutas de íconos SVG, el token CSRF u otras cifras).
     */
    private function cardValue(TestResponse $response, string $label): string
    {
        // Captura solo el texto plano justo después de abrir el div del valor, hasta la primera
        // etiqueta (el cierre </div> o un <small>, como en "47,00 <small>kg</small>").
        $pattern = '/<div[^>]*class="(?:label|m-lab)"[^>]*>'.preg_quote($label, '/').'<\/div>\s*'
            .'<div[^>]*class="(?:value|m-val)[^"]*"[^>]*>([^<]*)/s';
        preg_match($pattern, $response->getContent(), $matches);

        return trim($matches[1] ?? '');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_inactive_users_are_redirected_to_login(): void
    {
        $user = $this->userFor(Role::Producer);
        $user->update(['is_active' => false]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function everyRole(): array
    {
        return [
            'admin' => [Role::Admin],
            'productor' => [Role::Producer],
            'técnico' => [Role::Technician],
            'consulta' => [Role::Viewer],
        ];
    }

    #[DataProvider('everyRole')]
    public function test_every_role_can_open_the_dashboard(Role $role): void
    {
        $user = $role === Role::Admin ? User::factory()->role(Role::Admin)->create() : $this->userFor($role, $this->farmA);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Fincas')
            ->assertSee('Lotes activos')
            ->assertSee('Biomasa estimada')
            ->assertSee('Alertas pendientes')
            ->assertSee('Calidad del agua')
            ->assertSee('Actividad productiva reciente')
            ->assertSee('Últimas alertas')
            ->assertSee('Accesos rápidos');
    }

    public function test_admin_sees_totals_across_every_farm(): void
    {
        $foreignFarm = Farm::factory()->create();
        Batch::factory()->for(Pond::factory()->for($foreignFarm))->create(['initial_quantity' => 300, 'initial_average_weight_g' => 3]);

        // batch1 (1000) + batch3 (500) + el de otro productor (300) = 3 lotes activos, 1.800 peces.
        $response = $this->actingAs(User::factory()->role(Role::Admin)->create())->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame('3', $this->cardValue($response, 'Fincas'));
        $this->assertSame('3', $this->cardValue($response, 'Lotes activos'));
        $this->assertSame('1.800', $this->cardValue($response, 'Peces sembrados'));
    }

    public function test_producer_sees_totals_of_their_own_farms_only(): void
    {
        $foreignFarm = Farm::factory()->create();
        Batch::factory()->for(Pond::factory()->for($foreignFarm))->create(['initial_quantity' => 300, 'initial_average_weight_g' => 3]);

        $response = $this->actingAs($this->userFor(Role::Producer))->get(route('dashboard'));

        $response->assertOk();
        // Fincas y estanques: solo las 2 fincas del productor.
        $this->assertSame('2', $this->cardValue($response, 'Fincas'));
        $this->assertSame('2', $this->cardValue($response, 'Estanques'));
        // Lotes activos: batch1 y batch3, sin contar el cerrado ni el ajeno.
        $this->assertSame('2', $this->cardValue($response, 'Lotes activos'));
        // Peces sembrados: 1000 + 500 = 1500.
        $this->assertSame('1.500', $this->cardValue($response, 'Peces sembrados'));
        // Peces vivos: (1000-100) + 500 = 1400.
        $this->assertSame('1.400', $this->cardValue($response, 'Peces vivos estimados'));
        // Biomasa: 900 × 50 g = 45 kg (batch1, con biometría) + 500 × 4 g = 2 kg (batch3, sin biometría).
        $this->assertSame('47,00', $this->cardValue($response, 'Biomasa estimada'));
    }

    public function test_closed_and_cancelled_batches_are_excluded_from_active_totals(): void
    {
        Batch::factory()->for($this->pondA)->cancelled()->create(['initial_quantity' => 7777, 'initial_average_weight_g' => 7777]);

        $response = $this->actingAs($this->userFor(Role::Producer))->get(route('dashboard'));

        $response->assertOk()->assertDontSee('7.777')->assertDontSee('9.999');
        $this->assertSame('2', $this->cardValue($response, 'Lotes activos'));
    }

    public function test_technicians_and_viewers_see_only_assigned_farms(): void
    {
        $technician = $this->userFor(Role::Technician, $this->farmA);

        $response = $this->actingAs($technician)->get(route('dashboard'));
        $response->assertOk();
        $this->assertSame('1', $this->cardValue($response, 'Fincas'));
        $this->assertSame('1', $this->cardValue($response, 'Lotes activos'));
        $this->assertSame('1.000', $this->cardValue($response, 'Peces sembrados'));

        // Al quitar la asignación, las cifras vuelven a cero.
        $this->farmA->users()->detach($technician);
        $response = $this->get(route('dashboard'));
        $this->assertSame('0', $this->cardValue($response, 'Fincas'));
        $this->assertSame('0', $this->cardValue($response, 'Lotes activos'));
    }

    public function test_unassigned_technicians_and_viewers_see_empty_totals(): void
    {
        $viewer = User::factory()->role(Role::Viewer)->forProducer($this->producer)->create();

        $response = $this->actingAs($viewer)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('No hay actividad productiva')
            ->assertSee('No hay alertas registradas');
        $this->assertSame('0', $this->cardValue($response, 'Fincas'));
        $this->assertSame('0', $this->cardValue($response, 'Lotes activos'));
    }

    public function test_a_producer_without_producer_sees_only_zeros(): void
    {
        $response = $this->actingAs(User::factory()->role(Role::Producer)->create())->get(route('dashboard'));

        $response->assertOk()->assertSee('Todavía no tienes fincas asignadas.');
        $this->assertSame('0', $this->cardValue($response, 'Fincas'));
        $this->assertSame('0', $this->cardValue($response, 'Lotes activos'));
    }

    public function test_pending_and_critical_alert_counts_are_correct_and_isolated(): void
    {
        $metric = Metric::factory()->create();
        $reading = fn (Batch $batch) => WaterQualityReading::factory()->for($batch)->for($metric, 'metric')->create();

        Alert::factory()->create([
            'batch_id' => $this->batch1->id, 'metric_id' => $metric->id, 'water_quality_reading_id' => $reading($this->batch1)->id,
            'severity' => WaterQualityStatus::Warning, 'status' => AlertStatus::Pending,
        ]);
        Alert::factory()->create([
            'batch_id' => $this->batch1->id, 'metric_id' => $metric->id, 'water_quality_reading_id' => $reading($this->batch1)->id,
            'severity' => WaterQualityStatus::Critical, 'status' => AlertStatus::Pending,
        ]);
        // Ya resuelta: no cuenta como pendiente.
        Alert::factory()->create([
            'batch_id' => $this->batch1->id, 'metric_id' => $metric->id, 'water_quality_reading_id' => $reading($this->batch1)->id,
            'severity' => WaterQualityStatus::Critical, 'status' => AlertStatus::Resolved, 'resolved_at' => now(),
        ]);
        // De otro productor: no debe contarse ni verse.
        $foreignBatch = Batch::factory()->create();
        Alert::factory()->create([
            'batch_id' => $foreignBatch->id, 'metric_id' => $metric->id, 'water_quality_reading_id' => $reading($foreignBatch)->id,
            'severity' => WaterQualityStatus::Critical, 'status' => AlertStatus::Pending,
        ]);

        $response = $this->actingAs($this->userFor(Role::Producer))->get(route('dashboard'));

        $response->assertOk()->assertDontSee($foreignBatch->code);
        $this->assertSame('2', $this->cardValue($response, 'Alertas pendientes'));
        $this->assertSame('1', $this->cardValue($response, 'Alertas críticas pendientes'));
    }

    public function test_water_quality_summary_is_classified_with_the_farms_alert_rule(): void
    {
        $metric = Metric::factory()->create(['min_value' => 0, 'max_value' => 40]);
        AlertRule::factory()->for($this->farmA)->for($metric)->create(['warn_min' => 20, 'crit_min' => 10]);

        WaterQualityReading::factory()->for($this->batch1)->for($metric, 'metric')->create(['value' => 30]); // normal
        WaterQualityReading::factory()->for($this->batch1)->for($metric, 'metric')->create(['value' => 15]); // advertencia
        WaterQualityReading::factory()->for($this->batch1)->for($metric, 'metric')->create(['value' => 5]);  // crítico
        // Fuera de la ventana de 7 días: no debe contarse.
        WaterQualityReading::factory()->for($this->batch1)->for($metric, 'metric')->create(['value' => 30, 'measured_at' => now()->subDays(30)]);

        $response = $this->actingAs($this->userFor(Role::Producer))->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame('3', $this->cardValue($response, 'Mediciones (7 días)'));
        $this->assertSame('1', $this->cardValue($response, 'Normales'));
        $this->assertSame('1', $this->cardValue($response, 'Advertencia'));
        $this->assertSame('1', $this->cardValue($response, 'Crítico'));
    }

    public function test_recent_activity_shows_the_latest_productive_events(): void
    {
        Feeding::factory()->for($this->batch1)->create(['fed_on' => now()->subDay()]);

        $this->actingAs($this->userFor(Role::Producer))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alimentación en '.$this->batch1->code)
            ->assertSee('Mortalidad en '.$this->batch1->code)
            ->assertSee('Biometría en '.$this->batch1->code);
    }

    public function test_recent_activity_and_alerts_do_not_leak_another_producers_data(): void
    {
        $foreignFarm = Farm::factory()->create();
        $foreignBatch = Batch::factory()->for(Pond::factory()->for($foreignFarm))->create(['code' => 'AJENO-1']);
        Feeding::factory()->for($foreignBatch)->create();
        $metric = Metric::factory()->create();
        $reading = WaterQualityReading::factory()->for($foreignBatch)->for($metric, 'metric')->create();
        Alert::factory()->create([
            'batch_id' => $foreignBatch->id, 'metric_id' => $metric->id, 'water_quality_reading_id' => $reading->id,
            'severity' => WaterQualityStatus::Critical,
        ]);

        $this->actingAs($this->userFor(Role::Producer))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('AJENO-1');
    }

    public function test_dashboard_does_not_run_n_plus_one_queries(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('dashboard'))->assertOk();

            return count(DB::getQueryLog());
        };

        $seed = function (): void {
            $farm = Farm::factory()->create();
            $pond = Pond::factory()->for($farm)->create();
            $batch = Batch::factory()->for($pond)->create();
            Feeding::factory()->for($batch)->create();
            Mortality::factory()->for($batch)->create();
            Sampling::factory()->for($batch)->create();
            $metric = Metric::factory()->create();
            AlertRule::factory()->for($farm)->for($metric)->create(['crit_max' => 1]);
            $reading = WaterQualityReading::factory()->for($batch)->for($metric, 'metric')->create(['value' => 10]);
            Alert::factory()->create([
                'batch_id' => $batch->id, 'metric_id' => $metric->id, 'water_quality_reading_id' => $reading->id,
                'severity' => WaterQualityStatus::Critical,
            ]);
        };

        $admin = User::factory()->role(Role::Admin)->create();
        $this->actingAs($admin);

        // La base ya incluye al menos un registro de cada tipo: así el conteo no se falsea por
        // consultas de eager loading que solo aparecen cuando hay algo que cargar (0 -> 1 fila).
        $seed();
        $few = $count();

        foreach (range(1, 8) as $i) {
            $seed();
        }

        $this->assertSame($few, $count());
    }
}
