<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the first administrator on a hosted database, where the demo
 * seeder must not be run. The password is prompted for (hidden) so it
 * never appears in shell history or deployment logs.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'inquiry:create-admin
                            {--name= : Full name}
                            {--email= : Login email address}';

    protected $description = 'Create an administrator account (for hosted deployments)';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->secret('Password (min 12 chars, upper, lower and a number)');
        $confirm = $this->secret('Confirm password');

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirm,
            ],
            [
                'name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => strtolower($email),
            'password' => $password, // hashed by the model cast
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->info("Administrator {$email} created.");

        return self::SUCCESS;
    }
}
