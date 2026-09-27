<?php

declare(strict_types=1);

use App\Models\Beneficiary;
use App\Models\User;
use App\UserRole;
use Database\Seeders\DemoSeeder;

test('DemoSeeder يضيف مستفيدين وهميين معتمدين دون إنشاء أي حساب مدير', function (): void {
    $this->seed(DemoSeeder::class);

    expect(Beneficiary::query()->count())->toBeGreaterThan(0)
        ->and(Beneficiary::query()->whereNull('approved_at')->count())->toBe(0)
        ->and(User::query()->where('role', UserRole::Admin)->exists())->toBeFalse();
});

test('DemoSeeder آمن للتشغيل أكثر من مرة', function (): void {
    $this->seed(DemoSeeder::class);
    $countAfterFirstRun = Beneficiary::query()->count();

    $this->seed(DemoSeeder::class);

    expect(Beneficiary::query()->count())->toBe($countAfterFirstRun)
        ->and(User::query()->where('role', UserRole::Admin)->exists())->toBeFalse();
});
