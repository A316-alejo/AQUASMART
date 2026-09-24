<?php

namespace Tests\Feature\Catalogs;

use App\Enums\Catalog;
use App\Enums\Role;
use App\Models\FeedType;
use App\Models\Metric;
use App\Models\MortalityCause;
use App\Models\Species;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(Role::Admin)->create();
    }

    /**
     * Datos válidos de alta por catálogo y el campo que identifica el registro.
     *
     * @return array<string, array{0: Catalog, 1: array<string, mixed>, 2: array<string, mixed>}>
     */
    public static function validPayloads(): array
    {
        return [
            'especies' => [Catalog::Species,
                ['common_name' => '  Tilapia   roja ', 'scientific_name' => 'Oreochromis sp.', 'is_active' => '1'],
                ['common_name' => 'Tilapia roja', 'scientific_name' => 'Oreochromis sp.']],
            'alimentos' => [Catalog::FeedTypes,
                ['name' => 'Levante', 'brand' => '', 'protein_pct' => '34,5', 'pellet_mm' => '3', 'is_active' => '1'],
                ['name' => 'Levante', 'brand' => null, 'protein_pct' => '34.50', 'pellet_mm' => '3.00']],
            'causas' => [Catalog::MortalityCauses,
                ['name' => 'Baja de oxígeno', 'is_active' => '0'],
                ['name' => 'Baja de oxígeno', 'is_active' => false]],
            'parámetros' => [Catalog::Metrics,
                ['code' => ' Dissolved_Oxygen ', 'name' => 'Oxígeno disuelto', 'unit' => 'mg/L', 'decimals' => '2', 'min_value' => '0', 'max_value' => '25,5', 'is_active' => '1'],
                ['code' => 'dissolved_oxygen', 'unit' => 'mg/L', 'decimals' => 2, 'max_value' => '25.500']],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $expected
     */
    #[DataProvider('validPayloads')]
    public function test_admin_can_create_items_in_every_catalog(Catalog $catalog, array $payload, array $expected): void
    {
        $this->actingAs($this->admin)
            ->get(route('catalogs.create', $catalog))
            ->assertOk()
            ->assertSee($catalog->description());

        $this->post(route('catalogs.store', $catalog), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('catalogs.index', $catalog))
            ->assertSessionHas('status');

        $item = $catalog->query()->sole();

        foreach ($expected as $attribute => $value) {
            $this->assertSame($value, $item->{$attribute}, $attribute);
        }
    }

    public function test_catalog_listing_shows_tabs_counts_and_items(): void
    {
        Species::factory()->create(['common_name' => 'Tilapia nilótica', 'scientific_name' => 'Oreochromis niloticus']);
        Species::factory()->inactive()->create(['common_name' => 'Bocachico']);
        Metric::factory()->create(['code' => 'ph', 'name' => 'pH', 'unit' => null, 'min_value' => 0, 'max_value' => 14]);

        $this->actingAs($this->admin)
            ->get(route('catalogs.home'))
            ->assertRedirect(route('catalogs.index', Catalog::Species));

        $this->get(route('catalogs.index', Catalog::Species))
            ->assertOk()
            ->assertSee('Tilapia nilótica')
            ->assertSee('Oreochromis niloticus')
            ->assertSee('Bocachico')
            ->assertSee('Inactiva')
            ->assertSee('Nueva especie')
            ->assertSeeInOrder(['Especies', '2', 'Tipos de alimento', '0', 'Causas de mortalidad', '0', 'Parámetros del agua', '1']);

        $this->get(route('catalogs.index', Catalog::Metrics))
            ->assertOk()
            ->assertSee('ph')
            ->assertSee('0,00 a 14,00')
            ->assertDontSee('Tilapia nilótica');
    }

    public function test_admin_can_edit_items(): void
    {
        $feed = FeedType::factory()->create(['name' => 'Engorde', 'protein_pct' => 30]);

        $this->actingAs($this->admin)->get(route('catalogs.edit', [Catalog::FeedTypes, $feed]))->assertOk()->assertSee('Engorde');

        $this->put(route('catalogs.update', [Catalog::FeedTypes, $feed]), [
            'name' => 'Engorde', 'brand' => 'Genérica', 'protein_pct' => '32', 'pellet_mm' => '',
        ])->assertSessionHasNoErrors()->assertRedirect(route('catalogs.index', Catalog::FeedTypes));

        $feed->refresh();
        $this->assertSame('Genérica', $feed->brand);
        $this->assertSame('32.00', $feed->protein_pct);
        $this->assertNull($feed->pellet_mm);
        $this->assertTrue($feed->is_active);
    }

    public function test_metric_code_cannot_be_changed(): void
    {
        $metric = Metric::factory()->create(['code' => 'temperature', 'name' => 'Temperatura']);

        $this->actingAs($this->admin)
            ->get(route('catalogs.edit', [Catalog::Metrics, $metric]))
            ->assertOk()
            ->assertDontSee('name="code"', false);

        $this->put(route('catalogs.update', [Catalog::Metrics, $metric]), [
            'code' => 'temp', 'name' => 'Temperatura del agua', 'unit' => '°C', 'decimals' => '1',
        ])->assertSessionHasErrors(['code' => 'El código de un parámetro no se puede cambiar.']);

        $this->put(route('catalogs.update', [Catalog::Metrics, $metric]), [
            'name' => 'Temperatura del agua', 'unit' => '°C', 'decimals' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('temperature', $metric->fresh()->code);
        $this->assertSame('Temperatura del agua', $metric->fresh()->name);
    }

    public function test_admin_can_deactivate_and_activate_items(): void
    {
        $cause = MortalityCause::factory()->create(['name' => 'Depredación']);

        $this->actingAs($this->admin)
            ->get(route('catalogs.deactivate.confirm', [Catalog::MortalityCauses, $cause]))
            ->assertOk()
            ->assertSee('¿Desactivar la causa de mortalidad Depredación?');

        $this->patch(route('catalogs.deactivate', [Catalog::MortalityCauses, $cause]))
            ->assertRedirect(route('catalogs.index', Catalog::MortalityCauses))
            ->assertSessionHas('status', 'La causa de mortalidad Depredación fue desactivada.');
        $this->assertFalse($cause->fresh()->is_active);
        $this->patch(route('catalogs.deactivate', [Catalog::MortalityCauses, $cause]))->assertForbidden();

        $this->patch(route('catalogs.activate', [Catalog::MortalityCauses, $cause]))
            ->assertSessionHas('status', 'La causa de mortalidad Depredación fue activada.');
        $this->assertTrue($cause->fresh()->is_active);
        $this->patch(route('catalogs.activate', [Catalog::MortalityCauses, $cause]))->assertForbidden();
    }

    public function test_names_are_unique_within_each_catalog(): void
    {
        Species::factory()->create(['common_name' => 'Tilapia roja']);
        $cachama = Species::factory()->create(['common_name' => 'Cachama blanca']);
        FeedType::factory()->create(['name' => 'Levante', 'brand' => null]);
        Metric::factory()->create(['code' => 'ph', 'name' => 'pH']);

        $this->actingAs($this->admin);

        $this->post(route('catalogs.store', Catalog::Species), ['common_name' => 'TILAPIA ROJA', 'is_active' => '1'])
            ->assertSessionHasErrors(['common_name' => 'Ya existe una especie con ese nombre común.']);
        $this->put(route('catalogs.update', [Catalog::Species, $cachama]), ['common_name' => 'Tilapia roja'])
            ->assertSessionHasErrors('common_name');
        $this->put(route('catalogs.update', [Catalog::Species, $cachama]), ['common_name' => 'Cachama blanca'])
            ->assertSessionHasNoErrors();

        // El mismo nombre de alimento se admite con otra marca, no repetido sin marca.
        $this->post(route('catalogs.store', Catalog::FeedTypes), ['name' => 'Levante', 'protein_pct' => '34', 'is_active' => '1'])
            ->assertSessionHasErrors(['name' => 'Ya existe un tipo de alimento con ese nombre y marca.']);
        $this->post(route('catalogs.store', Catalog::FeedTypes), ['name' => 'Levante', 'brand' => 'Marca A', 'protein_pct' => '34', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        // Un nombre repetido en otro catálogo no choca.
        $this->post(route('catalogs.store', Catalog::MortalityCauses), ['name' => 'Levante', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $this->post(route('catalogs.store', Catalog::Metrics), ['code' => 'PH', 'name' => 'PH', 'decimals' => '2', 'is_active' => '1'])
            ->assertSessionHasErrors([
                'code' => 'Ya existe un parámetro con ese código.',
                'name' => 'Ya existe un parámetro con ese nombre.',
            ]);

        $this->assertSame(2, Species::count());
        $this->assertSame(2, FeedType::count());
    }

    public function test_invalid_data_is_rejected_with_spanish_messages(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('catalogs.store', Catalog::Species), [])
            ->assertSessionHasErrors([
                'common_name' => 'El campo nombre común es obligatorio.',
                'is_active' => 'El campo estado es obligatorio.',
            ]);

        $this->post(route('catalogs.store', Catalog::FeedTypes), ['name' => 'X', 'protein_pct' => '120', 'pellet_mm' => '-1', 'is_active' => '1'])
            ->assertSessionHasErrors([
                'name' => 'El campo nombre debe tener al menos 2 caracteres.',
                'protein_pct' => 'El campo proteína no debe ser mayor que 100.',
                'pellet_mm' => 'El campo tamaño del pellet debe ser mayor que 0.',
            ]);

        $this->post(route('catalogs.store', Catalog::Metrics), [
            'code' => '1 oxígeno', 'name' => 'Oxígeno', 'decimals' => '7', 'min_value' => '10', 'max_value' => '5', 'is_active' => '1',
        ])->assertSessionHasErrors([
            'code' => 'El código debe empezar por una letra y solo puede tener minúsculas, números y guiones bajos.',
            'decimals' => 'El campo decimales debe estar entre 0 y 4.',
            'max_value' => 'El valor máximo debe ser mayor que el valor mínimo.',
        ]);

        $this->assertSame(0, Species::count() + FeedType::count() + Metric::count());
    }

    public function test_items_are_searchable_filterable_and_paginated(): void
    {
        Species::factory()->create(['common_name' => 'Tilapia roja', 'scientific_name' => 'Oreochromis sp.']);
        Species::factory()->inactive()->create(['common_name' => 'Trucha arcoíris', 'scientific_name' => 'Oncorhynchus mykiss']);

        $this->actingAs($this->admin);

        $this->get(route('catalogs.index', [Catalog::Species, 'q' => 'oncorhynchus']))->assertSee('Trucha arcoíris')->assertDontSee('Tilapia roja');
        $this->get(route('catalogs.index', [Catalog::Species, 'status' => 'active']))->assertSee('Tilapia roja')->assertDontSee('Trucha arcoíris');
        $this->get(route('catalogs.index', [Catalog::Species, 'q' => 'no-existe']))->assertSee('No hay resultados');

        foreach (range(1, 17) as $i) {
            MortalityCause::factory()->create(['name' => sprintf('Causa %02d', $i)]);
        }

        $this->get(route('catalogs.index', Catalog::MortalityCauses))->assertSee('Causa 15')->assertDontSee('Causa 16')->assertSee('Mostrando 1–15 de 17');
        $this->get(route('catalogs.index', [Catalog::MortalityCauses, 'page' => 2]))->assertSee('Causa 17')->assertDontSee('Causa 01');
    }

    public function test_listing_runs_a_constant_number_of_queries(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('catalogs.index', Catalog::FeedTypes))->assertOk();

            return count(DB::getQueryLog());
        };

        $this->actingAs($this->admin);
        FeedType::factory()->count(2)->create();
        $few = $count();

        FeedType::factory()->count(10)->create();
        $this->assertSame($few, $count());
    }

    public function test_items_cannot_be_deleted(): void
    {
        $species = Species::factory()->create();

        $this->actingAs($this->admin)->delete('/catalogos/especies/'.$species->id)->assertStatus(405);
        $this->assertModelExists($species);
    }

    public function test_items_are_resolved_only_inside_their_own_catalog(): void
    {
        $species = Species::factory()->create();
        $cause = MortalityCause::factory()->create();

        $this->actingAs($this->admin);

        // El ID de una especie no sirve para editar otro catálogo.
        $this->get('/catalogos/causas-mortalidad/'.($cause->id + $species->id + 100).'/editar')->assertNotFound();
        $this->get('/catalogos/desconocido')->assertNotFound();
        $this->get('/catalogos/desconocido/'.$species->id.'/editar')->assertNotFound();
        $this->get('/catalogos/especies/abc/editar')->assertNotFound();
    }

    public function test_seeder_loads_tilapia_catalogs_without_duplicates(): void
    {
        $this->seed(CatalogSeeder::class);
        Species::where('common_name', 'Tilapia roja')->update(['scientific_name' => 'Editado por el admin']);
        $this->seed(CatalogSeeder::class);

        $this->assertSame(6, Species::count());
        $this->assertSame(5, FeedType::count());
        $this->assertSame(8, MortalityCause::count());
        $this->assertSame(6, Metric::count());
        $this->assertSame('Editado por el admin', Species::where('common_name', 'Tilapia roja')->value('scientific_name'));
        $this->assertEqualsCanonicalizing(
            ['temperature', 'ph', 'dissolved_oxygen', 'ammonia', 'nitrite', 'water_level'],
            Metric::pluck('code')->all(),
        );
    }
}
