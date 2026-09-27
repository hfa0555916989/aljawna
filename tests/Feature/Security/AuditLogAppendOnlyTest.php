<?php

declare(strict_types=1);

use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| سجل التدقيق للإضافة فقط على مستوى قاعدة البيانات (T14 — FR-23)
|--------------------------------------------------------------------------
| كل محاولة تُلفّ في DB::transaction حتى يتراجع PostgreSQL إلى نقطة حفظ بعد
| رفض المشغّل، فتبقى معاملة الاختبار صالحة للتحقق بعدها.
*/

function attemptOnAuditLogs(Closure $statement): Closure
{
    return fn () => DB::transaction($statement);
}

test('UPDATE مباشر عبر DB::table على سجل التدقيق يرفضه المشغّل ولا يتغير السجل', function (): void {
    $entry = AuditLog::factory()->create(['action' => 'permissions.updated', 'ip' => '10.0.0.1']);

    expect(attemptOnAuditLogs(fn () => DB::table('audit_logs')->where('id', $entry->id)->update(['action' => 'tampered'])))
        ->toThrow(QueryException::class, 'audit_logs is append-only: UPDATE is not allowed');

    expect(DB::table('audit_logs')->where('id', $entry->id)->value('action'))->toBe('permissions.updated');
});

test('DELETE مباشر عبر DB::table على سجل التدقيق يرفضه المشغّل ويبقى السجل', function (): void {
    $entry = AuditLog::factory()->create();

    expect(attemptOnAuditLogs(fn () => DB::table('audit_logs')->where('id', $entry->id)->delete()))
        ->toThrow(QueryException::class, 'audit_logs is append-only: DELETE is not allowed');

    expect(DB::table('audit_logs')->where('id', $entry->id)->exists())->toBeTrue();
});

test('SQL خام بلا Eloquent ولا منشئ الاستعلام يُرفض كذلك', function (string $sql): void {
    AuditLog::factory()->count(2)->create();

    expect(attemptOnAuditLogs(fn () => DB::statement($sql)))
        ->toThrow(QueryException::class, 'audit_logs is append-only');

    expect(DB::table('audit_logs')->count())->toBe(2);
})->with([
    'UPDATE' => ["UPDATE audit_logs SET ip = '0.0.0.0'"],
    'DELETE' => ['DELETE FROM audit_logs'],
    'TRUNCATE' => ['TRUNCATE audit_logs'],
]);

test('رفض المشغّل يحمل رمز SQLSTATE لرفض الصلاحية', function (): void {
    $entry = AuditLog::factory()->create();

    try {
        DB::transaction(fn () => DB::table('audit_logs')->where('id', $entry->id)->delete());
        $this->fail('DELETE على audit_logs لم يُرفض.');
    } catch (QueryException $exception) {
        expect($exception->getCode())->toBe('42501');
    }
});

test('التعديل والحذف عبر Eloquent يُرفضان من قاعدة البيانات', function (): void {
    $entry = AuditLog::factory()->create(['action' => 'permissions.updated']);

    expect(attemptOnAuditLogs(fn () => $entry->update(['action' => 'tampered'])))->toThrow(QueryException::class);
    expect(attemptOnAuditLogs(fn () => $entry->delete()))->toThrow(QueryException::class);

    expect(AuditLog::query()->find($entry->id)?->action)->toBe('permissions.updated');
});

test('الإضافة إلى سجل التدقيق تبقى مسموحة', function (): void {
    DB::table('audit_logs')->insert(['action' => 'permissions.updated', 'meta' => '{}']);

    expect(DB::table('audit_logs')->where('action', 'permissions.updated')->count())->toBe(1);
});
