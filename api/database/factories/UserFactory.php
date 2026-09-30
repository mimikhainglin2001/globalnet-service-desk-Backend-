<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::CUSTOMER,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function customer(): static
    {
        return $this->state(fn () => ['role' => Role::CUSTOMER]);
    }

    public function agent(): static
    {
        return $this->state(fn () => ['role' => Role::AGENT]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::ADMIN]);
    }

    public function inTeam(Team $team): static
    {
        return $this->afterCreating(fn (User $user) => $user->teams()->attach($team));
    }
}
