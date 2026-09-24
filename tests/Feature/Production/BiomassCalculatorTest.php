<?php

namespace Tests\Feature\Production;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Mortality;
use App\Models\Sampling;
use App\Models\User;
use App\Services\BiomassCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BiomassCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private BiomassCalculator $calculator;

    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new BiomassCalculator;
        $this->batch = Batch::factory()->create([
            'stocked_at' => today()->subDays(90)->toDateString(),
            'initial_quantity' => 10000,
            'initial_average_weight_g' => 3,
        ]);
    }

    public function test_uses_the_initial_weight_when_there_are_no_samplings(): void
    {
        $estimate = $this->calculator->estimate($this->batch);

        $this->assertSame(10000, $estimate->initialQuantity);
        $this->assertSame(0, $estimate->cumulativeMortality);
        $this->assertSame(10000, $estimate->liveFish);
        $this->assertSame(3.0, $estimate->averageWeightG);
        $this->assertFalse($estimate->usesSampling());
        $this->assertSame(30.0, $estimate->biomassKg);
        $this->assertSame(100.0, $estimate->survivalPct());
    }

    public function test_live_fish_discount_the_cumulative_mortality(): void
    {
        Mortality::factory()->for($this->batch)->create(['quantity' => 150]);
        Mortality::factory()->for($this->batch)->create(['quantity' => 350]);
        Mortality::factory()->create(['quantity' => 999]); // otro lote

        $estimate = $this->calculator->estimate($this->batch);

        $this->assertSame(500, $estimate->cumulativeMortality);
        $this->assertSame(9500, $estimate->liveFish);
        $this->assertSame(28.5, $estimate->biomassKg); // 9500 × 3 g
        $this->assertSame(95.0, $estimate->survivalPct());
        $this->assertSame(9500, $this->calculator->liveFish($this->batch));
    }

    public function test_uses_the_most_recent_sampling_by_date(): void
    {
        Mortality::factory()->for($this->batch)->create(['quantity' => 1000]);
        Sampling::factory()->for($this->batch)->create(['sampled_on' => today()->subDays(10)->toDateString(), 'average_weight_g' => 180]);
        // Registrada después, pero con fecha anterior: no es la más reciente.
        Sampling::factory()->for($this->batch)->create(['sampled_on' => today()->subDays(40)->toDateString(), 'average_weight_g' => 60]);

        $estimate = $this->calculator->estimate($this->batch);

        $this->assertSame(180.0, $estimate->averageWeightG);
        $this->assertTrue($estimate->usesSampling());
        $this->assertSame(today()->subDays(10)->toDateString(), $estimate->weightSampledOn->toDateString());
        $this->assertSame(1620.0, $estimate->biomassKg); // 9000 × 180 g

        // A igual fecha, gana la última registrada.
        Sampling::factory()->for($this->batch)->create(['sampled_on' => today()->subDays(10)->toDateString(), 'average_weight_g' => 185.5]);
        $this->assertSame(185.5, $this->calculator->estimate($this->batch->fresh())->averageWeightG);
    }

    public function test_biomass_is_calculated_not_stored_and_follows_new_records(): void
    {
        $this->assertSame(30.0, $this->calculator->estimate($this->batch)->biomassKg);

        Sampling::factory()->for($this->batch)->create(['average_weight_g' => 50]);
        $this->assertSame(500.0, $this->calculator->estimate($this->batch->fresh())->biomassKg);

        Mortality::factory()->for($this->batch)->create(['quantity' => 2000]);
        $this->assertSame(400.0, $this->calculator->estimate($this->batch->fresh())->biomassKg);

        $this->assertNotContains('biomass', array_map('strtolower', array_keys($this->batch->fresh()->getAttributes())));
    }

    public function test_preloaded_aggregates_avoid_queries(): void
    {
        Mortality::factory()->for($this->batch)->create(['quantity' => 100]);
        Sampling::factory()->for($this->batch)->create(['average_weight_g' => 20]);

        $batch = Batch::query()->withSum('mortalities', 'quantity')->with('latestSampling')->findOrFail($this->batch->id);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $estimate = $this->calculator->estimate($batch);

        $this->assertCount(0, DB::getQueryLog());
        $this->assertSame(198.0, $estimate->biomassKg); // 9900 × 20 g
    }

    public function test_batch_detail_shows_the_productive_summary(): void
    {
        Mortality::factory()->for($this->batch)->create(['quantity' => 500]);
        Sampling::factory()->for($this->batch)->create(['sampled_on' => today()->subDays(5)->toDateString(), 'average_weight_g' => 120]);

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get(route('batches.show', $this->batch))
            ->assertOk()
            ->assertSeeInOrder([
                'Peces iniciales', '10.000',
                'Mortalidad acumulada', '500', '5,0 % de la siembra',
                'Peces vivos estimados', '9.500', 'Sobrevivencia 95,0 %',
                'Peso promedio actual', '120,00', 'Biometría del '.today()->subDays(5)->format('d/m/Y'),
                'Biomasa estimada', '1.140,00',
            ]);
    }

    public function test_batch_detail_explains_when_the_initial_weight_is_used(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get(route('batches.show', $this->batch))
            ->assertSee('Peso de siembra (aún sin biometrías)')
            ->assertSee('30,00');
    }
}
