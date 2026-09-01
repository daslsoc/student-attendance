<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * A role with no permissions at all — tests opt in to exactly the atoms
     * they need with ->withAtoms([...]).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'description' => fake()->sentence(),
            'permission_list' => ',',
        ];
    }

    /**
     * @param  list<string>  $atoms
     */
    public function withAtoms(array $atoms): static
    {
        return $this->state(fn () => [
            'permission_list' => Role::atomsToCsv($atoms),
        ]);
    }

    /** Every atom the app defines. */
    public function administrator(): static
    {
        return $this->state(fn () => [
            'name' => 'Administrator '.fake()->unique()->numberBetween(1, 100000),
            'permission_list' => Role::atomsToCsv(Role::knownAtoms()),
        ]);
    }
}
