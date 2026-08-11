<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_number' => 'REG' . fake()->unique()->numberBetween(2024000, 2024999),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'department' => 'IT',
            'programme' => 'BSCS',
            'password' => static::$password ??= Hash::make('password'),
            'is_verified' => true,
        ];
    }
}
