<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
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

        $this->registerGates();
    }

    private function registerGates(): void
    {
        Gate::define('manage-users', fn (User $user) => $user->is_active && $user->isAdmin());

        Gate::define('assign-role', fn (User $user, Role $role) => Gate::forUser($user)->allows('manage-users')
            && $role->level() <= $user->role->level());

        Gate::define('manage-producers', fn (User $user) => $user->is_active && $user->isAdmin());
    }
}
