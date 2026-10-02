<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'name'            => fake()->name(),
            'email'           => fake()->unique()->safeEmail(),
            'password'        => static::$password ??= Hash::make('password'),
            'role'            => 'owner',
            'is_active'       => true,
            'is_super_admin'  => false,
            'remember_token'  => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => 'owner']);
    }

    public function manager(): static
    {
        return $this->state(['role' => 'manager']);
    }

    public function cashier(): static
    {
        return $this->state(['role' => 'cashier']);
    }

    public function superAdmin(): static
    {
        return $this->state(['is_super_admin' => true, 'role' => 'owner']);
    }
}
