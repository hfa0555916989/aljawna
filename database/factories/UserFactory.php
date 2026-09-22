<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * ملّاح تسلسلي لضمان جوالات فريدة (05XXXXXXXX ثم +9665XXXXXXXX).
     */
    protected static int $phoneSequence = 500000000;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => $this->fakeFullName(),
            'phone' => $this->nextPhone(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::User,
            'is_active' => true,
            'show_contact' => false,
            'registered_ip' => fake()->ipv4(),
            'last_login_ip' => null,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * حالة: مشرف.
     */
    public function supervisor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Supervisor,
        ]);
    }

    /**
     * حالة: مدير.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * حالة: حساب معطَّل.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * اسم كامل عربي مكوَّن من أربع كلمات فأكثر، يوافق قاعدة App\Rules\FullName.
     */
    private function fakeFullName(): string
    {
        $arabicNames = [
            'عبدالله', 'محمد', 'أحمد', 'خالد', 'سعود', 'فيصل', 'ناصر', 'إبراهيم',
            'العجاوني', 'الحربي', 'الزهراني', 'القرني', 'العتيبي', 'الدوسري',
        ];

        return implode(' ', fake()->randomElements($arabicNames, 4));
    }

    /**
     * جوال سعودي فريد بصيغة `+9665XXXXXXXX`.
     */
    private function nextPhone(): string
    {
        return '+966'.(string) (static::$phoneSequence++);
    }
}
