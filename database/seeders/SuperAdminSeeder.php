<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@merqiopos.com';

        if (User::where('email', $email)->exists()) {
            $this->command->info('Super admin already exists: ' . $email);
            return;
        }

        // A fixed password in source control is a standing back door into the
        // most privileged account, so a random one is generated and shown once.
        $password = \Illuminate\Support\Str::password(20);

        User::create([
            'name'           => 'Super Admin',
            'email'          => $email,
            'password'       => Hash::make($password),
            'role'           => 'owner',
            'is_active'      => true,
            'is_super_admin' => true,
        ]);

        $this->command->info('');
        $this->command->info('✅  Super admin created:');
        $this->command->info('   Email    : ' . $email);
        $this->command->info('   Password : ' . $password);
        $this->command->info('   ⚠️  Save this password now — it is not stored anywhere; enable 2FA on first login.');
        $this->command->info('');
    }
}
