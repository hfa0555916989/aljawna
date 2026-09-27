<?php

declare(strict_types=1);

use App\Models\PasswordResetRequest;
use App\Models\RecoveryLog;
use App\Models\User;
use App\PasswordResetStatus;
use App\RecoveryLogAction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| سجل الاستعادة للإضافة فقط على مستوى قاعدة البيانات (T14 — docs/SPEC.md §4.3)
|--------------------------------------------------------------------------
| حماية Eloquent وحدها يتجاوزها منشئ الاستعلام أو SQL الخام، فالمشغّل هو الضمان.
| كل محاولة تُلفّ في DB::transaction لتبقى معاملة الاختبار صالحة بعد الرفض.
*/

function attemptOnRecoveryLogs(Closure $statement): Closure
{
    return fn () => DB::transaction($statement);
}

function appendOnlyRecoveryLog(): RecoveryLog
{
    $owner = User::factory()->create();
    $supervisor = User::factory()->supervisor()->create();

    $request = PasswordResetRequest::query()->create([
        'user_id' => $owner->id,
        'status' => PasswordResetStatus::LinkSent,
        'requested_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
    ]);

    return RecoveryLog::query()->create([
        'request_id' => $request->id,
        'user_id' => $owner->id,
        'performed_by' => $supervisor->id,
        'action' => RecoveryLogAction::LinkRegistered,
        'sent_to_phone' => $owner->phone,
        'reason' => 'سبب أصلي',
    ]);
}

test('UPDATE مباشر عبر DB::table على سجل الاستعادة يرفضه المشغّل ولا يتغير السجل', function (): void {
    $entry = appendOnlyRecoveryLog();

    expect(attemptOnRecoveryLogs(fn () => DB::table('recovery_logs')->where('id', $entry->id)->update(['reason' => 'تلاعب'])))
        ->toThrow(QueryException::class, 'recovery_logs is append-only: UPDATE is not allowed');

    expect(DB::table('recovery_logs')->where('id', $entry->id)->value('reason'))->toBe('سبب أصلي');
});

test('DELETE مباشر عبر DB::table على سجل الاستعادة يرفضه المشغّل ويبقى السجل', function (): void {
    $entry = appendOnlyRecoveryLog();

    expect(attemptOnRecoveryLogs(fn () => DB::table('recovery_logs')->where('id', $entry->id)->delete()))
        ->toThrow(QueryException::class, 'recovery_logs is append-only: DELETE is not allowed');

    expect(DB::table('recovery_logs')->where('id', $entry->id)->exists())->toBeTrue();
});

test('SQL خام على سجل الاستعادة يُرفض كذلك', function (string $sql): void {
    appendOnlyRecoveryLog();

    expect(attemptOnRecoveryLogs(fn () => DB::statement($sql)))
        ->toThrow(QueryException::class, 'recovery_logs is append-only');

    expect(DB::table('recovery_logs')->count())->toBe(1);
})->with([
    'UPDATE' => ["UPDATE recovery_logs SET reason = 'x'"],
    'DELETE' => ['DELETE FROM recovery_logs'],
    'TRUNCATE' => ['TRUNCATE recovery_logs'],
]);

test('رفض المشغّل على سجل الاستعادة يحمل رمز SQLSTATE لرفض الصلاحية', function (): void {
    $entry = appendOnlyRecoveryLog();

    try {
        DB::transaction(fn () => DB::table('recovery_logs')->where('id', $entry->id)->delete());
        $this->fail('DELETE على recovery_logs لم يُرفض.');
    } catch (QueryException $exception) {
        expect($exception->getCode())->toBe('42501');
    }
});

test('الإضافة إلى سجل الاستعادة تبقى مسموحة', function (): void {
    appendOnlyRecoveryLog();
    appendOnlyRecoveryLog();

    expect(RecoveryLog::query()->count())->toBe(2);
});
