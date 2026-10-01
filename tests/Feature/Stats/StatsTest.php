<?php

namespace Tests\Feature\Stats;

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
use App\Models\MortalityCause;
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

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private Producer $producer;

    private Farm $farmA;

    private Farm $farmB;

    private Pond $pondA;

    private Pond $pondB;

    private Batch $batch1;

    private Batch $batch2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->producer = Producer::factory()->create();
        $this->farmA = Farm::factory()->for($this->producer)->create();
        $this->farmB = Farm::factory()->for($this->producer)->create();
        $this->pondA = Pond::factory()->for($this->farmA)->create(['code' => 'E01']);
        $this->pondB = Pond::factory()->for($this->farmB)->create(['code' => 'E02']);

        $this->batch1 = Batch::factory()->for($this->pondA)->create([
            'code' => 'L1', 'stocked_at' => today()->subDays(60), 'initial_quantity' => 1000, 'initial_average_weight_g' => 2,
        ]);
        $this->batch2 = Batch::factory()->for($this->pondB)->create([
            'code' => 'L2', 'stocked_at' => today()->subDays(60), 'initial_quantity' => 500, 'initial_average_weight_g' => 3,
        ]);
    }

    private function userFor(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->role($role)->forProducer($this->producer)->create();

        if ($role !== Role::Producer && $farm) {
            $farm->users()->attach($user);
        }

        return $user;
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

    private function metricValue(TestResponse $response, string $label): string
    {
        $pattern = '/<div[^>]*class="m-lab"[^>]*>'.preg_quote($label, '/').'<\/div>\s*'
            .'<div[^>]*class="m-val[^"]*"[^>]*>([^<]*)/s';
        preg_match($pattern, $response->getContent(), $matches);

        return trim($matches[1] ?? '');
    }

    public function test_guests_cannot_access_stats(): void
    {
        $this->get(route('stats'))->assertRedirect(route('login'));
    }

    public function test_inactive_users_are_redirected_to_login(): void
    {
        $user = User::factory()->role(Role::Admin)->create(['is_active' => false]);

        $this->actingAs($user)->get(route('stats'))->assertRedirect(route('login'));
    }

    #[DataProvider('everyRole')]
    public function test_every_role_can_open_stats(Role $role): void
    {
        $user = $this->userFor($role, $this->farmA);

        $this->actingAs($user)->get(route('stats'))->assertOk()->assertSee('Estadísticas');
    }

    public function test_only_get_is_registered_for_the_stats_route(): void
    {
        $this->assertSame(['GET', 'HEAD'], \Illuminate\Support\Facades\Route::getRoutes()->getByName('stats')->methods());
    }

    public function test_production_summary_is_calculated_correctly_with_known_data(): void
    {
        // batch1: 1000 sembrados, 100 (en rango) + 50 (fuera de rango, hace 40 días) de mortalidad acumulada,
        // biometría reciente de 50 g -> vivos 850, biomasa 42.5 kg.
        Mortality::factory()->for($this->batch1)->create(['quantity' => 100, 'recorded_on' => today()->subDays(5)]);
        Mortality::factory()->for($this->batch1)->create(['quantity' => 50, 'recorded_on' => today()->subDays(40)]);
        Sampling::factory()->for($this->batch1)->create(['average_weight_g' => 50, 'sampled_on' => today()->subDays(3)]);

        // batch2: 500 sembrados, sin registros -> vivos 500, usa el peso de siembra (3 g), biomasa 1.5 kg.

        $admin = User::factory()->role(Role::Admin)->create();

        $response = $this->actingAs($admin)->get(route('stats'))->assertOk();

        $this->assertSame('1.500', $this->metricValue($response, 'Peces sembrados'));
        $this->assertSame('150', $this->metricValue($response, 'Mortalidad acumulada'));
        $this->assertSame('1.350', $this->metricValue($response, 'Peces vivos estimados'));
        $this->assertSame('44,00', $this->metricValue($response, 'Biomasa estimada'));
        $this->assertSame('90,0%', $this->metricValue($response, 'Supervivencia'));
        $this->assertSame('32,59 g', $this->metricValue($response, 'Peso promedio actual'));
    }

    public function test_the_feeding_and_mortality_sections_are_scoped_to_the_date_range_unlike_production(): void
    {
        // La mortalidad "acumulada" de Producción no distingue fechas; la sección Mortalidad sí.
        Mortality::factory()->for($this->batch1)->create(['quantity' => 100, 'recorded_on' => today()->subDays(5)]);
        Mortality::factory()->for($this->batch1)->create(['quantity' => 50, 'recorded_on' => today()->subDays(40)]);

        $admin = User::factory()->role(Role::Admin)->create();

        $response = $this->actingAs($admin)->get(route('stats'))->assertOk();

        $this->assertSame('150', $this->metricValue($response, 'Mortalidad acumulada'));
        // El total de la sección "Mortalidad" (en la cabecera, con el tag) solo cuenta los 100 dentro del rango por defecto.
        $response->assertSeeInOrder(['Mortalidad', '100']);
        $this->assertDatabaseCount('mortalities', 2);
    }

    public function test_feeding_totals_by_period_and_by_batch_are_correct(): void
    {
        Feeding::factory()->for($this->batch1)->create(['quantity_g' => 2000, 'fed_on' => today()->subDays(2)]);
        Feeding::factory()->for($this->batch1)->create(['quantity_g' => 3000, 'fed_on' => today()->subDays(1)]);
        Feeding::factory()->for($this->batch2)->create(['quantity_g' => 1000, 'fed_on' => today()->subDays(1)]);

        $admin = User::factory()->role(Role::Admin)->create();

        $response = $this->actingAs($admin)->get(route('stats'))->assertOk();
        $response->assertSee('6,00 kg');
        $response->assertSeeInOrder(['L1', '5,00 kg']);
        $response->assertSeeInOrder(['L2', '1,00 kg']);
    }

    public function test_mortality_by_cause_and_by_batch_are_correct(): void
    {
        $diseaseCause = MortalityCause::factory()->create(['name' => 'Enfermedad']);
        $oxygenCause = MortalityCause::factory()->create(['name' => 'Baja de oxígeno']);

        Mortality::factory()->for($this->batch1)->for($diseaseCause, 'cause')->create(['quantity' => 30, 'recorded_on' => today()->subDays(1)]);
        Mortality::factory()->for($this->batch1)->for($oxygenCause, 'cause')->create(['quantity' => 20, 'recorded_on' => today()->subDays(1)]);

        $admin = User::factory()->role(Role::Admin)->create();

        $response = $this->actingAs($admin)->get(route('stats'))->assertOk();
        $response->assertSeeInOrder(['Enfermedad', '30']);
        $response->assertSeeInOrder(['Baja de oxígeno', '20']);
        $response->assertSeeInOrder(['Mortalidad por lote', 'L1', '50']);
    }

    public function test_water_quality_is_classified_and_charted_per_metric(): void
    {
        $metric = Metric::factory()->create(['name' => 'pH', 'unit' => '', 'decimals' => 2]);
        AlertRule::factory()->for($this->farmA)->for($metric, 'metric')->create(['warn_max' => 8, 'crit_max' => 9]);

        WaterQualityReading::factory()->for($this->batch1)->for($metric, 'metric')->create(['value' => 7, 'measured_at' => today()->subDays(2)]);
        WaterQualityReading::factory()->for($this->batch1)->for($metric, 'metric')->create(['value' => 9.5, 'measured_at' => today()->subDay()]);

        $admin = User::factory()->role(Role::Admin)->create();

        $response = $this->actingAs($admin)->get(route('stats'))->assertOk();

        $this->assertSame('1', $this->metricValue($response, 'Normales'));
        $this->assertSame('0', $this->metricValue($response, 'Advertencia'));
        $this->assertSame('1', $this->metricValue($response, 'Crítico'));
        $response->assertSee('pH (');
    }

    public function test_alerts_totals_and_evolution_are_correct(): void
    {
        Alert::factory()->critical()->create(['batch_id' => $this->batch1->id, 'created_at' => today()->subDays(2), 'status' => AlertStatus::Pending]);
        Alert::factory()->create(['batch_id' => $this->batch1->id, 'created_at' => today()->subDay(), 'status' => AlertStatus::Resolved, 'severity' => WaterQualityStatus::Warning]);

        $admin = User::factory()->role(Role::Admin)->create();

        $response = $this->actingAs($admin)->get(route('stats'))->assertOk();

        $this->assertSame('1', $this->metricValue($response, 'Críticas'));
        $this->assertSame('1', $this->metricValue($response, 'Advertencias'));
        $this->assertSame('1', $this->metricValue($response, 'Pendientes'));
        $this->assertSame('1', $this->metricValue($response, 'Resueltas'));
    }

    public function test_filter_by_farm_scopes_every_section(): void
    {
        Mortality::factory()->for($this->batch1)->create(['quantity' => 40, 'recorded_on' => today()->subDay()]);
        Mortality::factory()->for($this->batch2)->create(['quantity' => 999, 'recorded_on' => today()->subDay()]);

        $admin = User::factory()->role(Role::Admin)->create();

        $response = $this->actingAs($admin)->get(route('stats', ['farm' => $this->farmA->id]))->assertOk();

        $this->assertSame('1.000', $this->metricValue($response, 'Peces sembrados'));
        $response->assertDontSee('999');
    }

    public function test_filter_by_pond_scopes_every_section(): void
    {
        $admin = User::factory()->role(Role::Admin)->create();

        $response = $this->actingAs($admin)->get(route('stats', ['pond' => $this->pondB->id]))->assertOk();

        $this->assertSame('500', $this->metricValue($response, 'Peces sembrados'));
    }

    public function test_filter_by_batch_scopes_every_section(): void
    {
        $admin = User::factory()->role(Role::Admin)->create();

        $response = $this->actingAs($admin)->get(route('stats', ['batch' => $this->batch1->id]))->assertOk();

        $this->assertSame('1.000', $this->metricValue($response, 'Peces sembrados'));
    }

    public function test_filter_by_date_range_excludes_records_outside_it(): void
    {
        Feeding::factory()->for($this->batch1)->create(['quantity_g' => 5000, 'fed_on' => today()->subDays(90)]);

        $admin = User::factory()->role(Role::Admin)->create();

        $response = $this->actingAs($admin)->get(route('stats'))->assertOk();
        $response->assertSee('No hay registros de alimentación en el período seleccionado.');

        $response = $this->actingAs($admin)->get(route('stats', [
            'from' => today()->subDays(95)->toDateString(), 'to' => today()->subDays(85)->toDateString(),
        ]))->assertOk();
        $response->assertSee('5,00 kg');
    }

    public function test_a_producer_cannot_see_another_producers_data_even_by_guessing_ids(): void
    {
        $foreignFarm = Farm::factory()->create();
        $foreignPond = Pond::factory()->for($foreignFarm)->create();
        $foreignBatch = Batch::factory()->for($foreignPond)->create(['initial_quantity' => 7777]);

        $producer = $this->userFor(Role::Producer);

        // Un id de finca/lote ajeno nunca aparece en una intersección con visibleTo(): el resultado
        // es simplemente "sin datos", no un error ni datos de otro productor.
        $response = $this->actingAs($producer)->get(route('stats', ['farm' => $foreignFarm->id]))->assertOk();
        $response->assertDontSee('7.777');
        $response->assertSee('No hay datos para estos filtros');

        $response = $this->actingAs($producer)->get(route('stats', ['batch' => $foreignBatch->id]))->assertOk();
        $response->assertDontSee('7.777');
        $response->assertSee('No hay datos para estos filtros');
    }

    public function test_technician_sees_only_assigned_farms(): void
    {
        $technician = $this->userFor(Role::Technician, $this->farmA);

        $response = $this->actingAs($technician)->get(route('stats'))->assertOk();
        $this->assertSame('1.000', $this->metricValue($response, 'Peces sembrados'));

        $this->farmA->users()->detach($technician);
        $this->actingAs($technician)->get(route('stats'))->assertOk()->assertSee('No hay datos para estos filtros');
    }

    public function test_viewer_can_open_stats_read_only(): void
    {
        $viewer = $this->userFor(Role::Viewer, $this->farmA);

        $this->actingAs($viewer)->get(route('stats'))->assertOk()->assertSee('method="GET"', false);
    }

    public function test_empty_state_is_shown_when_the_user_has_no_visible_batches(): void
    {
        $user = User::factory()->role(Role::Producer)->create();

        $this->actingAs($user)->get(route('stats'))
            ->assertOk()
            ->assertSee('No hay datos para estos filtros');
    }

    public function test_does_not_run_a_query_per_record(): void
    {
        $seed = function (): void {
            $batch = Batch::factory()->for($this->pondA)->create();
            Feeding::factory()->for($batch)->create(['fed_on' => today()->subDay()]);
            Mortality::factory()->for($batch)->create(['recorded_on' => today()->subDay()]);
            $metric = Metric::factory()->create();
            WaterQualityReading::factory()->for($batch)->for($metric, 'metric')->create(['measured_at' => today()->subDay()]);
            Alert::factory()->create(['batch_id' => $batch->id, 'metric_id' => $metric->id, 'created_at' => today()->subDay()]);
        };

        $admin = User::factory()->role(Role::Admin)->create();
        $this->actingAs($admin);

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('stats'))->assertOk();

            return count(DB::getQueryLog());
        };

        $seed();
        $few = $count();

        foreach (range(1, 6) as $i) {
            $seed();
        }
        $many = $count();

        $this->assertSame($few, $many);
    }
}
