<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'زائر تجريبي',
            'phone' => '+9665'.fake()->numerify('########'),
            'body' => 'رسالة تجريبية للاختبار.',
            'ip' => fake()->ipv4(),
            'read_at' => null,
        ];
    }

    /**
     * مقروءة.
     */
    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }
}
