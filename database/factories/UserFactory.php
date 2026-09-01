<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * Teachers default to the Administrator role (seeded by the roles
     * migration), so a plain User::factory() behaves the way every teacher did
     * before roles existed: full access. Tests that care about permissions opt
     * into a narrower role with ->withAtoms([...]) or ->inRole($role).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => fn () => Role::where('name', 'Administrator')->value('id'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Put the teacher in a brand new role carrying exactly these atoms.
     *
     * @param  list<string>  $atoms
     */
    public function withAtoms(array $atoms): static
    {
        return $this->state(fn () => [
            'role_id' => Role::factory()->withAtoms($atoms)->create()->id,
        ]);
    }

    public function inRole(Role $role): static
    {
        return $this->state(fn () => ['role_id' => $role->id]);
    }

    public function roleless(): static
    {
        return $this->state(fn () => ['role_id' => null]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => ['deactivated_at' => now()]);
    }
}
