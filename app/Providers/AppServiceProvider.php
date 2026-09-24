<?php

namespace App\Providers;

use App\Enums\Catalog;
use App\Enums\Role;
use App\Models\User;
use App\Policies\CatalogPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // En desarrollo, falla ante consultas N+1 y atributos inexistentes en lugar de ocultarlos.
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        Route::pattern('user', '[0-9]+');
        Route::pattern('producer', '[0-9]+');
        Route::pattern('farm', '[0-9]+');
        Route::pattern('pond', '[0-9]+');
        Route::pattern('item', '[0-9]+');
        Route::pattern('batch', '[0-9]+');

        // {item} se busca únicamente en la tabla del catálogo indicado en la URL.
        Route::bind('item', function (string $value, RoutingRoute $route) {
            $catalog = Catalog::tryFrom((string) $route->parameter('catalog')) ?? abort(404);

            return $catalog->query()->findOrFail($value);
        });

        $this->registerGates();
        $this->registerPolicies();
    }

    private function registerPolicies(): void
    {
        foreach (Catalog::cases() as $catalog) {
            Gate::policy($catalog->model(), CatalogPolicy::class);
        }
    }

    private function registerGates(): void
    {
        Gate::define('manage-users', fn (User $user) => $user->is_active && $user->isAdmin());

        Gate::define('assign-role', fn (User $user, Role $role) => Gate::forUser($user)->allows('manage-users')
            && $role->level() <= $user->role->level());

        Gate::define('manage-producers', fn (User $user) => $user->is_active && $user->isAdmin());

        Gate::define('manage-farms', fn (User $user) => $user->is_active && $user->isAdmin());

        Gate::define('manage-ponds', fn (User $user) => $user->is_active && $user->isAdmin());

        Gate::define('manage-catalogs', fn (User $user) => $user->is_active && $user->isAdmin());

        Gate::define('manage-batches', fn (User $user) => $user->is_active && $user->isAdmin());
    }
}
