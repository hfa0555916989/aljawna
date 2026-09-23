<?php

declare(strict_types=1);

namespace Database\Factories;

use App\BeneficiaryStatus;
use App\Models\Beneficiary;
use App\Models\User;
use App\Support\SaudiIban;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * مستفيدون وهميون بحسابات صحيحة الصيغة (لا بيانات حقيقية). الافتراضي: متاح وغير معتمد.
 *
 * @extends Factory<Beneficiary>
 */
class BeneficiaryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = implode(' ', fake()->randomElements(['سالم', 'ماجد', 'عبدالرحمن', 'تركي', 'نايف', 'العجاوني', 'المطيري', 'الشهري'], 4));
        $targetDeadline = fake()->dateTimeBetween('+2 months', '+6 months');

        return [
            'display_name' => $name,
            'account_holder' => $name,
            'bank_name' => 'مصرف الراجحي',
            'account_number' => fake()->numerify('###############'),
            'iban' => SaudiIban::fromParts('80', fake()->numerify('##################')),
            'target_amount' => fake()->randomElement(['30000.00', '45000.00', '60000.00']),
            'target_deadline' => $targetDeadline,
            'recommended_deadline' => (clone $targetDeadline)->modify('-1 month'),
            'wedding_date' => (clone $targetDeadline)->modify('+1 month'),
            'status' => BeneficiaryStatus::Active,
            'created_by' => User::factory()->admin(),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    /**
     * حالة: معتمد.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'approved_by' => $attributes['created_by'],
            'approved_at' => now(),
        ]);
    }

    /**
     * حالة: مغلق.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BeneficiaryStatus::Closed,
        ]);
    }
}
