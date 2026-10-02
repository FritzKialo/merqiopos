<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Super Admin ───────────────────────────────────────────────────────
        User::create([
            'name'           => 'Super Admin',
            'email'          => 'admin@sme.test',
            'password'       => Hash::make('password'),
            'role'           => 'owner',
            'is_active'      => true,
            'is_super_admin' => true,
        ]);

        // ── Demo owner + org + store (Growth plan) ────────────────────────────
        $owner = User::create([
            'name'      => 'Jane Wanjiru',
            'email'     => 'owner@sme.test',
            'password'  => Hash::make('password'),
            'role'      => 'owner',
            'is_active' => true,
        ]);

        $org = Organization::create([
            'name'              => 'Wanjiru Enterprises',
            'owner_user_id'     => $owner->id,
            'subscription_plan' => 'growth',
            'status'            => 'active',
        ]);

        $owner->update(['organization_id' => $org->id]);

        $store = Business::create([
            'organization_id' => $org->id,
            'name'            => 'Wanjiru Supermarket',
            'email'           => 'store@sme.test',
            'phone'           => '0712345678',
            'business_type'   => 'grocery',
            'status'          => 'active',
            'payroll_settings' => [
                'enabled'      => true,
                'pay_cycle'    => 'monthly',
                'employer_pin' => 'A001234567T',
                'deductions'   => ['paye' => true, 'nssf' => true, 'shif' => true],
            ],
        ]);

        // Attach owner to store pivot
        $store->users()->attach($owner->id, ['role' => 'owner']);

        // ── Demo cashier assigned to the store ────────────────────────────────
        $cashier = User::create([
            'name'      => 'Brian Otieno',
            'email'     => 'cashier@sme.test',
            'password'  => Hash::make('password'),
            'role'      => 'cashier',
            'is_active' => true,
        ]);
        $store->users()->attach($cashier->id, ['role' => 'cashier']);

        $this->command->info('');
        $this->command->info('✅  Seed complete. Login credentials:');
        $this->command->info('   Super Admin : admin@sme.test   / password');
        $this->command->info('   Owner       : owner@sme.test   / password');
        $this->command->info('   Cashier     : cashier@sme.test / password');
        $this->command->info('');
    }
}

