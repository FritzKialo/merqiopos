<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeSuperAdmin extends Command
{
    protected $signature   = 'admin:make {email? : Email of existing user to promote, or leave blank to create new}';
    protected $description = 'Create or promote a user to super admin';

    public function handle(): int
    {
        $email = $this->argument('email');

        if ($email) {
            // Promote existing user
            $user = User::where('email', $email)->first();

            if (! $user) {
                $this->error("No user found with email: {$email}");
                return 1;
            }

            $user->update(['is_super_admin' => true]);
            $this->info("✓ {$user->name} ({$email}) is now a super admin.");
            return 0;
        }

        // Create a new super admin account
        $this->info('Creating a new super admin account.');

        $name     = $this->ask('Full name');
        $email    = $this->ask('Email address');
        $password = $this->secret('Password (min 8 chars)');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists. Run: php artisan admin:make {$email}");
            return 1;
        }

        $user = User::create([
            'name'           => $name,
            'email'          => $email,
            'password'       => Hash::make($password),
            'role'           => 'owner',
            'is_active'      => true,
            'is_super_admin' => true,
            'business_id'    => null,
        ]);

        $this->info("✓ Super admin created: {$user->name} ({$user->email})");
        $this->line("  Login at /login and visit /admin");

        return 0;
    }
}
