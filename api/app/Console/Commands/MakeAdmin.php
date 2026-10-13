<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class MakeAdmin extends Command
{
    protected $signature = 'periscope:make-admin {email} {--name=} {--password=}';

    protected $description = 'Promote a user to admin, or create the admin account if the email is new';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::firstWhere('email', $email);

        if ($user === null) {
            $user = User::create([
                'name' => $this->option('name') ?: text('Name', required: true),
                'email' => $email,
                'password' => $this->option('password') ?: password('Password (min 8 characters)', required: true),
            ]);
            $user->forceFill(['email_verified_at' => now()]);
        }

        $user->forceFill(['role' => UserRole::Admin])->save();

        $this->info("{$email} is an admin. They will set up the authenticator app at first login.");

        return self::SUCCESS;
    }
}
