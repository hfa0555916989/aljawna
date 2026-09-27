<?php

declare(strict_types=1);

use App\Models\LoginAttempt;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\PasswordResetStatus;
use App\Services\SuspiciousAccounts;

/*
|--------------------------------------------------------------------------
| قواعد الحسابات المشبوهة (T14): العتبة والفترة من config/security.php
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->travelTo(now()->setDateTime(2026, 9, 27, 12, 0));

    config([
        'security.suspicious.failed_logins' => ['threshold' => 3, 'hours' => 24],
        'security.suspicious.registrations_per_ip' => ['threshold' => 3, 'hours' => 24],
        'security.suspicious.recovery_requests' => ['threshold' => 2, 'hours' => 48],
    ]);
});

/**
 * @return array<int, list<array{rule: string, count: int}>>
 */
function suspiciousFlagsById(): array
{
    $flags = [];

    foreach (app(SuspiciousAccounts::class)->detect() as $row) {
        $flags[$row['user']->id] = $row['flags'];
    }

    return $flags;
}

function failedLoginsFor(User $user, int $count): void
{
    LoginAttempt::factory()->count($count)->create(['phone' => $user->phone]);
}

function recoveryRequestsFor(User $user, int $count): void
{
    foreach (range(1, $count) as $ignored) {
        PasswordResetRequest::query()->create([
            'user_id' => $user->id,
            'status' => PasswordResetStatus::Expired,
            'requested_ip' => '127.0.0.1',
            'expires_at' => now()->addDay(),
        ]);
    }
}

test('لا حسابات مشبوهة دون نشاط يبلغ أي عتبة', function (): void {
    User::factory()->count(2)->create();

    expect(app(SuspiciousAccounts::class)->detect())->toBe([]);
});

test('المحاولات الفاشلة: بلوغ العتبة يُعلِّم الحساب وما دونها لا', function (): void {
    $flagged = User::factory()->create();
    $belowThreshold = User::factory()->create();
    failedLoginsFor($flagged, 3);
    failedLoginsFor($belowThreshold, 2);

    expect(suspiciousFlagsById())->toBe([
        $flagged->id => [['rule' => SuspiciousAccounts::RULE_FAILED_LOGINS, 'count' => 3]],
    ]);
});

test('المحاولات الفاشلة: الناجحة وما قبل بداية الفترة لا تُحتسب، وبدايتها تُحتسب', function (): void {
    $user = User::factory()->create();

    $this->travelTo(now()->setDateTime(2026, 9, 26, 11, 59, 59));
    failedLoginsFor($user, 1);
    $this->travelTo(now()->setDateTime(2026, 9, 27, 12, 0));
    failedLoginsFor($user, 2);
    LoginAttempt::factory()->succeeded()->count(3)->create(['phone' => $user->phone]);

    expect(suspiciousFlagsById())->toBe([]);

    $this->travelTo(now()->setDateTime(2026, 9, 26, 12, 0));
    failedLoginsFor($user, 1);
    $this->travelTo(now()->setDateTime(2026, 9, 27, 12, 0));

    expect(suspiciousFlagsById())->toBe([
        $user->id => [['rule' => SuspiciousAccounts::RULE_FAILED_LOGINS, 'count' => 3]],
    ]);
});

test('التسجيل من IP واحد: كل حسابات الـIP تُعلَّم عند بلوغ العتبة', function (): void {
    $sameIp = User::factory()->count(3)->create(['registered_ip' => '198.51.100.7']);
    User::factory()->count(2)->create(['registered_ip' => '198.51.100.8']);

    $flags = suspiciousFlagsById();

    expect(array_keys($flags))->toEqualCanonicalizing($sameIp->pluck('id')->all());
    expect($flags[$sameIp->first()->id])->toBe([['rule' => SuspiciousAccounts::RULE_REGISTRATIONS_PER_IP, 'count' => 3]]);
});

test('التسجيل من IP واحد: التسجيلات القديمة خارج الفترة لا تُحتسب ولا تُعلَّم', function (): void {
    $this->travelTo(now()->setDateTime(2026, 9, 26, 11, 0));
    $old = User::factory()->create(['registered_ip' => '198.51.100.7']);
    $this->travelTo(now()->setDateTime(2026, 9, 27, 12, 0));

    User::factory()->count(2)->create(['registered_ip' => '198.51.100.7']);

    expect(suspiciousFlagsById())->toBe([]);

    $recent = User::factory()->create(['registered_ip' => '198.51.100.7']);

    expect(array_keys(suspiciousFlagsById()))->not->toContain($old->id)->toContain($recent->id);
});

test('طلبات الاستعادة المتكررة: بلوغ العتبة داخل الفترة يُعلِّم الحساب', function (): void {
    $flagged = User::factory()->create();
    $once = User::factory()->create();
    $old = User::factory()->create();
    recoveryRequestsFor($flagged, 2);
    recoveryRequestsFor($once, 1);

    $this->travelTo(now()->setDateTime(2026, 9, 25, 11, 0));
    recoveryRequestsFor($old, 1);
    $this->travelTo(now()->setDateTime(2026, 9, 27, 12, 0));
    recoveryRequestsFor($old, 1);

    expect(suspiciousFlagsById())->toBe([
        $flagged->id => [['rule' => SuspiciousAccounts::RULE_RECOVERY_REQUESTS, 'count' => 2]],
    ]);
});

test('القواعد تشمل المبادرين وحدهم لا المشرفين ولا المدير', function (): void {
    $supervisor = User::factory()->supervisor()->create(['registered_ip' => '198.51.100.9']);
    $admin = User::factory()->admin()->create(['registered_ip' => '198.51.100.9']);
    User::factory()->supervisor()->create(['registered_ip' => '198.51.100.9']);
    failedLoginsFor($supervisor, 5);
    failedLoginsFor($admin, 5);
    recoveryRequestsFor($supervisor, 3);

    expect(suspiciousFlagsById())->toBe([]);
});

test('الحساب الذي يبلغ أكثر من قاعدة يظهر مرة واحدة بكل قواعده', function (): void {
    $user = User::factory()->create(['registered_ip' => '198.51.100.7']);
    User::factory()->count(2)->create(['registered_ip' => '198.51.100.7']);
    failedLoginsFor($user, 4);
    recoveryRequestsFor($user, 2);

    expect(suspiciousFlagsById()[$user->id])->toBe([
        ['rule' => SuspiciousAccounts::RULE_FAILED_LOGINS, 'count' => 4],
        ['rule' => SuspiciousAccounts::RULE_REGISTRATIONS_PER_IP, 'count' => 3],
        ['rule' => SuspiciousAccounts::RULE_RECOVERY_REQUESTS, 'count' => 2],
    ]);
});

test('تغيير العتبة في الإعدادات يغيّر النتيجة', function (): void {
    $user = User::factory()->create();
    failedLoginsFor($user, 3);

    config(['security.suspicious.failed_logins.threshold' => 4]);

    expect(suspiciousFlagsById())->toBe([]);
});
