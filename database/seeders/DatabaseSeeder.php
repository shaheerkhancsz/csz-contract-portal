<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan db:seed --force
     * Creates the first admin (from ADMIN_EMAIL / ADMIN_NAME in .env) and the starter templates.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@creativesolutionzone.com');
        if (! User::where('email', $email)->exists()) {
            $password = env('ADMIN_PASSWORD') ?: Str::password(16, symbols: false);
            User::create([
                'name' => env('ADMIN_NAME', 'CSZ Admin'),
                'email' => $email,
                'password' => $password,
                'role' => Role::Admin,
                'is_active' => true,
            ]);
            $this->command?->warn("Admin user: $email");
            if (! env('ADMIN_PASSWORD')) {
                $this->command?->warn("Temporary password: $password   (change it after the first login)");
            }
        }

        $this->call(TemplateSeeder::class);
    }
}
