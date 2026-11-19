<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'shop:create-admin
                            {--name= : Display name of the admin}
                            {--email= : Login email of the admin}
                            {--password= : Password (avoid this option - it stays in your shell history; leave it out to be asked securely)}';

    protected $description = 'Create an admin account for the Perfume Shop admin panel';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Admin name', 'Admin');
        $email = strtolower((string) ($this->option('email') ?: $this->ask('Admin email')));
        $password = $this->option('password') ?: $this->secret('Password (min 8 characters, hidden)');

        if (! $this->option('password')) {
            $confirm = $this->secret('Confirm password');
            if ($confirm !== $password) {
                $this->error('The passwords do not match.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'max:72'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->info("Admin account created. Log in with: {$email}");

        return self::SUCCESS;
    }
}
