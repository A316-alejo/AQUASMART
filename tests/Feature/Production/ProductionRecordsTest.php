<?php

namespace Tests\Feature\Production;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\Feeding;
use App\Models\FeedType;
use App\Models\Mortality;
use App\Models\MortalityCause;
use App\Models\Sampling;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Batch $batch;

    private FeedType $feedType;

    private MortalityCause $cause;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(Role::Admin)->create(['name' => 'Ana Admin']);
        $this->batch = Batch::factory()->create([
            'code' => 'L-01',
            'stocked_at' => today()->subDays(60)->toDateString(),
            'initial_quantity' => 1000,
            'initial_average_weight_g' => 2.5,
        ]);
        $this->feedType = FeedType::factory()->create(['name' => 'Levante', 'brand' => null]);
        $this->cause = MortalityCause::factory()->create(['name' => 'Baja de oxígeno']);
    }

    /**
     * @return array<string, mixed>
     */
    private function feeding(array $overrides = []): array
    {
        return [
            'feeding_date' => today()->subDay()->toDateString(),
            'feed_type_id' => $this->feedType->id,
            'feeding_quantity_g' => '12500,5',
            'feeding_notes' => '  Dos raciones.  ',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mortality(array $overrides = []): array
    {
        return [
            'mortality_date' => today()->subDay()->toDateString(),
            'mortality_cause_id' => $this->cause->id,
            'mortality_quantity' => '25',
            'mortality_notes' => '',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampling(array $overrides = []): array
    {
        return [
            'sampling_date' => today()->subDay()->toDateString(),
            'sample_size' => '50',
            'average_weight_g' => '85,4',
            'average_length_cm' => '15,2',
            'sampling_notes' => '',
            ...$overrides,
        ];
    }

    public function test_feeding_can_be_recorded_and_consulted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('batches.feedings.store', $this->batch), $this->feeding())
            ->assertRedirect(route('batches.show', $this->batch).'#feeding')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Se registró la alimentación del lote L-01.');

        $feeding = Feeding::sole();
        $this->assertTrue($feeding->batch->is($this->batch));
        $this->assertTrue($feeding->feedType->is($this->feedType));
        $this->assertTrue($feeding->user->is($this->admin));
        $this->assertSame('12500.50', $feeding->quantity_g);
        $this->assertSame('Dos raciones.', $feeding->notes);

        $this->get(route('batches.show', $this->batch))
            ->assertOk()
            ->assertSee('Alimentación')
            ->assertSee('Levante')
            ->assertSee('12,50 kg')
            ->assertSee('Ana Admin')
            ->assertSee('Dos raciones.');
    }

    public function test_feeding_history_is_paginated_newest_first(): void
    {
        foreach (range(1, 12) as $day) {
            Feeding::factory()->for($this->batch)->for($this->feedType)->create([
                'fed_on' => today()->subDays($day)->toDateString(), 'notes' => sprintf('Ración %02d', $day),
            ]);
        }

        $this->actingAs($this->admin);
        $this->get(route('batches.show', $this->batch))->assertSeeInOrder(['Ración 01', 'Ración 10'])->assertDontSee('Ración 11');
        $this->get(route('batches.show', [$this->batch, 'alimentacion' => 2]))->assertSee('Ración 12')->assertDontSee('Ración 01');
    }

    public function test_mortality_can_be_recorded(): void
    {
        $this->actingAs($this->admin)
            ->post(route('batches.mortalities.store', $this->batch), $this->mortality())
            ->assertRedirect(route('batches.show', $this->batch).'#mortality')
            ->assertSessionHasNoErrors();

        $mortality = Mortality::sole();
        $this->assertSame(25, $mortality->quantity);
        $this->assertTrue($mortality->cause->is($this->cause));
        $this->assertNull($mortality->notes);

        $this->get(route('batches.show', $this->batch))->assertSee('Baja de oxígeno')->assertSee('975');
    }

    public function test_mortality_cannot_exceed_the_live_fish(): void
    {
        Mortality::factory()->for($this->batch)->for($this->cause, 'cause')->create(['quantity' => 990]);

        $this->actingAs($this->admin)
            ->post(route('batches.mortalities.store', $this->batch), $this->mortality(['mortality_quantity' => '11']))
            ->assertRedirect(route('batches.show', $this->batch).'#mortality')
            ->assertSessionHasErrors(['mortality_quantity' => 'La cantidad supera los peces vivos del lote (10).']);

        $this->post(route('batches.mortalities.store', $this->batch), $this->mortality(['mortality_quantity' => '10']))
            ->assertSessionHasNoErrors();

        $this->post(route('batches.mortalities.store', $this->batch), $this->mortality(['mortality_quantity' => '1']))
            ->assertSessionHasErrors('mortality_quantity');

        $this->assertSame(1000, (int) $this->batch->mortalities()->sum('quantity'));
    }

    public function test_sampling_can_be_recorded(): void
    {
        $this->actingAs($this->admin)
            ->post(route('batches.samplings.store', $this->batch), $this->sampling())
            ->assertRedirect(route('batches.show', $this->batch).'#sampling')
            ->assertSessionHasNoErrors();

        $sampling = Sampling::sole();
        $this->assertSame(50, $sampling->sample_size);
        $this->assertSame('85.40', $sampling->average_weight_g);
        $this->assertSame('15.20', $sampling->average_length_cm);

        $this->get(route('batches.show', $this->batch))->assertSee('85,40 g')->assertSee('15,20 cm');
    }

    public function test_sampling_must_be_coherent_with_the_population_and_weight(): void
    {
        Mortality::factory()->for($this->batch)->for($this->cause, 'cause')->create(['quantity' => 960]);

        $this->actingAs($this->admin)
            ->post(route('batches.samplings.store', $this->batch), $this->sampling(['sample_size' => '41']))
            ->assertSessionHasErrors(['sample_size' => 'La muestra no puede ser mayor que los peces vivos del lote (40).']);

        $this->post(route('batches.samplings.store', $this->batch), $this->sampling(['average_weight_g' => '0']))
            ->assertSessionHasErrors(['average_weight_g' => 'El campo peso promedio debe ser mayor que 0.']);
        $this->post(route('batches.samplings.store', $this->batch), $this->sampling(['average_weight_g' => '9000']))
            ->assertSessionHasErrors(['average_weight_g' => 'El campo peso promedio no debe ser mayor que 5000.']);
        $this->post(route('batches.samplings.store', $this->batch), $this->sampling(['average_weight_g' => 'pesado']))
            ->assertSessionHasErrors(['average_weight_g' => 'El campo peso promedio debe ser un número.']);
        $this->post(route('batches.samplings.store', $this->batch), $this->sampling(['sample_size' => '0', 'average_length_cm' => '-2']))
            ->assertSessionHasErrors([
                'sample_size' => 'El campo peces muestreados debe ser al menos 1.',
                'average_length_cm' => 'El campo longitud promedio debe ser mayor que 0.',
            ]);

        $this->assertSame(0, Sampling::count());
    }

    public function test_dates_must_be_between_stocking_and_today(): void
    {
        $this->actingAs($this->admin);
        $beforeStocking = today()->subDays(61)->toDateString();
        $tomorrow = today()->addDay()->toDateString();
        $stocked = today()->subDays(60)->format('d/m/Y');

        foreach ([
            ['batches.feedings.store', 'feeding_date', $this->feeding(...)],
            ['batches.mortalities.store', 'mortality_date', $this->mortality(...)],
            ['batches.samplings.store', 'sampling_date', $this->sampling(...)],
        ] as [$route, $field, $payload]) {
            $this->post(route($route, $this->batch), $payload([$field => $beforeStocking]))
                ->assertSessionHasErrors([$field => "La fecha no puede ser anterior a la siembra del lote ({$stocked})."]);
            $this->post(route($route, $this->batch), $payload([$field => $tomorrow]))
                ->assertSessionHasErrors([$field => 'La fecha no puede ser posterior a hoy.']);
            $this->post(route($route, $this->batch), $payload([$field => '31/12/2026']))
                ->assertSessionHasErrors([$field => 'La fecha no es válida.']);
            $this->post(route($route, $this->batch), $payload([$field => '']))
                ->assertSessionHasErrors([$field => 'El campo fecha es obligatorio.']);
        }

        // El mismo día de la siembra y hoy son válidos.
        $this->post(route('batches.feedings.store', $this->batch), $this->feeding(['feeding_date' => today()->subDays(60)->toDateString()]))->assertSessionHasNoErrors();
        $this->post(route('batches.feedings.store', $this->batch), $this->feeding(['feeding_date' => today()->toDateString()]))->assertSessionHasNoErrors();

        $this->assertSame(2, Feeding::count());
        $this->assertSame(0, Mortality::count() + Sampling::count());
    }

    public function test_invalid_data_is_rejected_with_spanish_messages(): void
    {
        $inactiveFeed = FeedType::factory()->inactive()->create();
        $inactiveCause = MortalityCause::factory()->inactive()->create();

        $this->actingAs($this->admin)
            ->post(route('batches.feedings.store', $this->batch), [])
            ->assertSessionHasErrors([
                'feeding_date' => 'El campo fecha es obligatorio.',
                'feed_type_id' => 'El campo tipo de alimento es obligatorio.',
                'feeding_quantity_g' => 'El campo cantidad de alimento es obligatorio.',
            ]);

        $this->post(route('batches.feedings.store', $this->batch), $this->feeding(['feed_type_id' => $inactiveFeed->id, 'feeding_quantity_g' => '-5']))
            ->assertSessionHasErrors([
                'feed_type_id' => 'El tipo de alimento seleccionado no existe o está inactivo.',
                'feeding_quantity_g' => 'El campo cantidad de alimento debe ser mayor que 0.',
            ]);

        $this->post(route('batches.mortalities.store', $this->batch), $this->mortality(['mortality_cause_id' => $inactiveCause->id, 'mortality_quantity' => '0']))
            ->assertSessionHasErrors([
                'mortality_cause_id' => 'La causa seleccionada no existe o está inactiva.',
                'mortality_quantity' => 'El campo cantidad de peces muertos debe ser al menos 1.',
            ]);

        $this->post(route('batches.mortalities.store', $this->batch), $this->mortality(['mortality_quantity' => '2,5']))
            ->assertSessionHasErrors(['mortality_quantity' => 'El campo cantidad de peces muertos debe ser un número entero.']);

        $this->assertSame(0, Feeding::count() + Mortality::count());
    }

    public function test_closed_and_cancelled_batches_do_not_accept_records(): void
    {
        $this->actingAs($this->admin);

        foreach ([Batch::factory()->closed()->create(), Batch::factory()->cancelled()->create()] as $batch) {
            $this->post(route('batches.feedings.store', $batch), $this->feeding(['feeding_date' => today()->subDays(2)->toDateString()]))->assertForbidden();
            $this->post(route('batches.mortalities.store', $batch), $this->mortality(['mortality_date' => today()->subDays(2)->toDateString()]))->assertForbidden();
            $this->post(route('batches.samplings.store', $batch), $this->sampling(['sampling_date' => today()->subDays(2)->toDateString()]))->assertForbidden();

            $this->get(route('batches.show', $batch))
                ->assertOk()
                ->assertSee('ni admite nuevos registros')
                ->assertDontSee('Registrar alimentación')
                ->assertDontSee('Registrar mortalidad')
                ->assertDontSee('Registrar biometría');
        }

        $this->assertSame(0, Feeding::count() + Mortality::count() + Sampling::count());
    }

    public function test_batch_edits_stay_consistent_with_existing_records(): void
    {
        Mortality::factory()->for($this->batch)->for($this->cause, 'cause')->create([
            'quantity' => 300, 'recorded_on' => today()->subDays(50)->toDateString(),
        ]);

        $payload = [
            'species_id' => $this->batch->species_id,
            'code' => 'L-01',
            'name' => 'Lote',
            'stocked_at' => today()->subDays(60)->toDateString(),
            'initial_quantity' => '299',
            'initial_average_weight_g' => '2,5',
        ];

        $this->actingAs($this->admin)
            ->put(route('batches.update', $this->batch), $payload)
            ->assertSessionHasErrors(['initial_quantity' => 'La cantidad sembrada no puede ser menor que la mortalidad ya registrada (300).']);

        $this->put(route('batches.update', $this->batch), [...$payload, 'initial_quantity' => '300', 'stocked_at' => today()->subDays(49)->toDateString()])
            ->assertSessionHasErrors(['stocked_at' => 'La fecha de siembra no puede ser posterior al primer registro productivo del lote ('.today()->subDays(50)->format('d/m/Y').').']);

        $this->put(route('batches.update', $this->batch), [...$payload, 'initial_quantity' => '300', 'stocked_at' => today()->subDays(50)->toDateString()])
            ->assertSessionHasNoErrors();
    }

    public function test_batch_detail_does_not_run_n_plus_one_queries(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('batches.show', $this->batch))->assertOk();

            return count(DB::getQueryLog());
        };

        $this->actingAs($this->admin);
        Feeding::factory()->count(2)->for($this->batch)->create(['user_id' => User::factory()]);
        Mortality::factory()->count(2)->for($this->batch)->create(['quantity' => 1, 'user_id' => User::factory()]);
        Sampling::factory()->count(2)->for($this->batch)->create(['user_id' => User::factory()]);
        $few = $count();

        Feeding::factory()->count(8)->for($this->batch)->create(['user_id' => User::factory()]);
        Mortality::factory()->count(8)->for($this->batch)->create(['quantity' => 1, 'user_id' => User::factory()]);
        Sampling::factory()->count(8)->for($this->batch)->create(['user_id' => User::factory()]);

        $this->assertSame($few, $count());
    }

    public function test_records_cannot_be_deleted_and_protect_their_batch(): void
    {
        Feeding::factory()->for($this->batch)->create();

        $this->actingAs($this->admin)->delete(route('batches.feedings.store', $this->batch))->assertStatus(405);

        $this->expectException(QueryException::class);
        $this->batch->delete();
    }
}
