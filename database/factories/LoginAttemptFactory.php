<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LoginAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginAttempt>
 */
class LoginAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '+9665'.fake()->numerify('########'),
            'ip' => fake()->ipv4(),
            'succeeded' => false,
        ];
    }

    /**
     * حالة: محاولة ناجحة.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'succeeded' => true,
        ]);
    }
}
