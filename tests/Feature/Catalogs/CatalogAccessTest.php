<?php

namespace Tests\Feature\Catalogs;

use App\Enums\Catalog;
use App\Enums\Role;
use App\Models\FeedType;
use App\Models\Metric;
use App\Models\MortalityCause;
use App\Models\Species;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogAccessTest extends TestCase
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
     * Un registro activo y uno inactivo de cada catálogo.
     *
     * @return list<array{0: Catalog, 1: Model}>
     */
    private function items(): array
    {
        $items = [];

        foreach ([
            [Catalog::Species, Species::factory()],
            [Catalog::FeedTypes, FeedType::factory()],
            [Catalog::MortalityCauses, MortalityCause::factory()],
            [Catalog::Metrics, Metric::factory()],
        ] as [$catalog, $factory]) {
            $items[] = [$catalog, $factory->create()];
            $items[] = [$catalog, $factory->inactive()->create()];
        }

        return $items;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function adminRoutes(Catalog $catalog, Model $item): array
    {
        return [
            ['get', route('catalogs.create', $catalog)],
            ['post', route('catalogs.store', $catalog)],
            ['get', route('catalogs.edit', [$catalog, $item])],
            ['put', route('catalogs.update', [$catalog, $item])],
            ['get', route('catalogs.deactivate.confirm', [$catalog, $item])],
            ['patch', route('catalogs.deactivate', [$catalog, $item])],
            ['patch', route('catalogs.activate', [$catalog, $item])],
        ];
    }

    public function test_guests_cannot_access_catalogs(): void
    {
        [$catalog, $item] = $this->items()[0];

        $this->get(route('catalogs.home'))->assertRedirect();
        $this->get(route('catalogs.index', $catalog))->assertRedirect(route('login'));

        foreach ($this->adminRoutes($catalog, $item) as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('login'));
        }
    }

    #[DataProvider('nonAdminRoles')]
    public function test_other_roles_can_read_catalogs_but_not_change_them(Role $role): void
    {
        $user = User::factory()->role($role)->create();
        $items = $this->items();

        foreach (Catalog::cases() as $catalog) {
            $this->actingAs($user)
                ->get(route('catalogs.index', $catalog))
                ->assertOk()
                ->assertDontSee('Acciones')
                ->assertDontSee(route('catalogs.create', $catalog));
        }

        foreach ($items as [$catalog, $item]) {
            foreach ($this->adminRoutes($catalog, $item) as [$method, $url]) {
                $this->actingAs($user)->{$method}($url, [
                    'common_name' => 'HACK', 'name' => 'HACK', 'code' => 'hack', 'protein_pct' => '30',
                    'decimals' => '2', 'is_active' => '1',
                ])->assertForbidden();
            }
        }

        $this->assertSame(0, Species::where('common_name', 'HACK')->count() + MortalityCause::where('name', 'HACK')->count());

        foreach ($items as [$catalog, $item]) {
            $this->assertSame($item->is_active, $item->fresh()->is_active);
        }
    }

    public function test_inactive_admins_cannot_manage_catalogs(): void
    {
        $this->assertFalse(User::factory()->role(Role::Admin)->inactive()->create()->can('manage-catalogs'));
    }

    public function test_menu_shows_catalogs_to_every_role(): void
    {
        foreach ([Role::Admin, Role::Producer, Role::Technician, Role::Viewer] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get(route('dashboard'))
                ->assertSee(route('catalogs.home'));
        }
    }
}
