<?php


namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) config('shop.admin.email')));
        $password = (string) config('shop.admin.password');

        if ($email === '' || $password === '') {
            $this->command?->warn('No admin created (ADMIN_EMAIL / ADMIN_PASSWORD are empty in .env). Run: php artisan shop:create-admin');

            return;
        }

        if (strlen($password) < 8) {
            $this->command?->error('ADMIN_PASSWORD must be at least 8 characters. No admin created.');

            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->command?->info("Admin/user {$email} already exists - left unchanged.");

            return;
        }

        User::create([
            'name' => (string) config('shop.admin.name', 'Admin'),
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->command?->info("Admin account created: {$email}");
    }
}
