<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
    }
}
