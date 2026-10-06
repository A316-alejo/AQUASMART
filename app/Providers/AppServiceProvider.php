<?php

namespace App\Providers;

use App\Enums\Catalog;
use App\Enums\Role;
use App\Mail\Transport\RedactedLogTransport;
use App\Models\User;
use App\Policies\CatalogPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Log\LogManager;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Psr\Log\LoggerInterface;

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

        // El driver de correo "log" (MAIL_MAILER=log, el de desarrollo) escribe el correo completo
        // en el log de la aplicación. Se sustituye por una versión que solo registra encabezados no
        // sensibles, para que un OTP, un enlace de restablecimiento u otro secreto nunca quede en
        // storage/logs. No afecta a un transporte real (SMTP/API) una vez configurado.
        Mail::extend('log', function (array $config) {
            $logger = $this->app->make(LoggerInterface::class);

            if ($logger instanceof LogManager) {
                $logger = $logger->channel($config['channel'] ?? $this->app['config']->get('mail.log_channel'));
            }

            return new RedactedLogTransport($logger);
        });

        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        Route::pattern('user', '[0-9]+');
        Route::pattern('producer', '[0-9]+');
        Route::pattern('farm', '[0-9]+');
        Route::pattern('pond', '[0-9]+');
        Route::pattern('item', '[0-9]+');
        Route::pattern('batch', '[0-9]+');
        Route::pattern('alert', '[0-9]+');
        Route::pattern('device', '[0-9]+');
        Route::pattern('sensor', '[0-9]+');

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

        // Configuración estructural de IoT (dispositivos y sensores): solo administradores.
        Gate::define('manage-devices', fn (User $user) => $user->is_active && $user->isAdmin());

        // Registros productivos (alimentación, mortalidad, biometría): roles operativos; consulta solo lee.
        Gate::define('record-production', fn (User $user) => $user->is_active
            && $user->hasRole(Role::Admin, Role::Producer, Role::Technician));
    }
}
