<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@aquasmart.test'],
            [
                'name' => 'Administrador AquaSmart',
                'password' => 'password',
                'role' => Role::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        if ($admin->wasRecentlyCreated) {
            $this->command->warn('Administrador creado: admin@aquasmart.test / password. Cambia esta contraseña.');
        }

        $this->call(CatalogSeeder::class);
    }
}
