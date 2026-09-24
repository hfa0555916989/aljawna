<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use App\TransferReviewState;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * حوالات وهمية لمستفيد معتمد متاح (لا بيانات حقيقية). الإيصال مسار عشوائي لا ملف فعلي.
 *
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'beneficiary_id' => Beneficiary::factory()->approved(),
            'amount' => fake()->randomElement(['100.00', '250.00', '500.00', '1000.00']),
            'transferred_on' => today()->subDays(fake()->numberBetween(0, 20)),
            'receipt_path' => 'receipts/'.Str::random(40).'.jpg',
            'receipt_hash' => hash('sha256', Str::random(64)),
            'bank_reference' => null,
            'is_repeated' => false,
            'review_state' => TransferReviewState::NotReviewed,
        ];
    }

    /**
     * حالة: عليها علامة "متكررة".
     */
    public function repeated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_repeated' => true,
        ]);
    }
}
