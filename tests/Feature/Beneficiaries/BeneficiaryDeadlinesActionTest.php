<?php

declare(strict_types=1);

use App\Actions\Beneficiaries\CreateBeneficiary;
use App\Actions\Beneficiaries\UpdateBeneficiary;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| المواعيد تُفرض داخل الإجراءات نفسها حتى عند استدعائها دون النموذج (قرار T05)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

/**
 * @return array<string, string>
 */
function deadlinesInDays(int $target, int $recommended, int $wedding): array
{
    return [
        'target_deadline' => today()->addDays($target)->toDateString(),
        'recommended_deadline' => today()->addDays($recommended)->toDateString(),
        'wedding_date' => today()->addDays($wedding)->toDateString(),
    ];
}

/**
 * @return array<string, list<string>>
 */
function validationErrorsOf(Closure $attempt): array
{
    try {
        $attempt();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('ValidationException not thrown.');
}

test('إجراء التسجيل يرفض مواعيد غير متسلسلة عند استدعائه مباشرة', function (int $target, int $recommended, int $wedding, string $field, string $message): void {
    $errors = validationErrorsOf(fn () => app(CreateBeneficiary::class)
        ->handle($this->admin, beneficiaryFormData(deadlinesInDays($target, $recommended, $wedding))));

    expect($errors)->toHaveKey($field)
        ->and($errors[$field])->toContain($message)
        ->and(Beneficiary::query()->exists())->toBeFalse()
        ->and(AuditLog::query()->exists())->toBeFalse();
})->with([
    'المستحسن قبل المستهدف' => [90, 60, 120, 'recommended_deadline', 'الموعد المستحسن يجب أن يكون في يوم الموعد المستهدف أو بعده.'],
    'الزواج قبل المستحسن' => [60, 120, 90, 'wedding_date', 'موعد الزواج يجب أن يكون في يوم الموعد المستحسن أو بعده.'],
]);

test('إجراء التسجيل يرفض أي موعد في الماضي عند استدعائه مباشرة', function (string $field): void {
    $errors = validationErrorsOf(fn () => app(CreateBeneficiary::class)
        ->handle($this->admin, beneficiaryFormData([$field => today()->subDay()->toDateString()])));

    expect($errors)->toHaveKey($field)
        ->and($errors[$field])->toContain('لا يجوز أن يكون هذا الموعد في الماضي.')
        ->and(Beneficiary::query()->exists())->toBeFalse();
})->with(['target_deadline', 'recommended_deadline', 'wedding_date']);

test('إجراء التسجيل يقبل مواعيد صحيحة تبدأ اليوم', function (): void {
    $beneficiary = app(CreateBeneficiary::class)->handle($this->admin, beneficiaryFormData(deadlinesInDays(0, 0, 30)));

    expect($beneficiary->exists)->toBeTrue();
});

test('إجراء التعديل يرفض مواعيد غير متسلسلة عند استدعائه مباشرة ولا يحفظ شيئًا', function (): void {
    $beneficiary = Beneficiary::factory()->create();
    $originalWedding = $beneficiary->wedding_date->toDateString();

    $errors = validationErrorsOf(fn () => app(UpdateBeneficiary::class)->handle($this->admin, $beneficiary, [
        'wedding_date' => $beneficiary->recommended_deadline->copy()->subDay()->toDateString(),
    ]));

    expect($errors)->toHaveKey('wedding_date')
        ->and($beneficiary->fresh()->wedding_date->toDateString())->toBe($originalWedding);
});

test('إجراء التعديل لا يرفض مستفيدًا مضت مواعيده ما دامت متسلسلة', function (): void {
    $beneficiary = Beneficiary::factory()->create([
        'target_deadline' => today()->subMonths(3),
        'recommended_deadline' => today()->subMonths(2),
        'wedding_date' => today()->subMonth(),
    ]);

    app(UpdateBeneficiary::class)->handle($this->admin, $beneficiary, ['target_amount' => '70000']);

    expect($beneficiary->fresh()->target_amount)->toBe('70000.00');
});
