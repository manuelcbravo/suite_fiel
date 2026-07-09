<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $admin = User::query()->where('email', 'admin@fielgroup.com.mx')->first()
            ?? User::factory()->create([
                'name' => 'Administrador',
                'email' => 'admin@fielgroup.com.mx',
                'password' => $this->passwordAdmin(),
            ]);

        $admin->forceFill(['es_super_admin' => true])->save();
    }

    /**
     * En producción la contraseña DEBE venir del entorno; el valor por
     * defecto débil existe solo para desarrollo local.
     */
    private function passwordAdmin(): string
    {
        $password = config('plataforma.admin_seed_password');

        if (is_string($password) && $password !== '') {
            return $password;
        }

        if (app()->isProduction()) {
            throw new RuntimeException('Define ADMIN_SEED_PASSWORD en el .env para sembrar en producción.');
        }

        return '12345678';
    }
}
