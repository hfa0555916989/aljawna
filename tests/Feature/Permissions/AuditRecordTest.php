<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit;

/*
|--------------------------------------------------------------------------
| Audit::record() (T03 — docs/SPEC.md §9 audit_logs)
|--------------------------------------------------------------------------
*/

test('يسجّل المنفّذ والموضوع والبيانات الإضافية', function (): void {
    $actor = User::factory()->admin()->create();
    $subject = User::factory()->supervisor()->create();

    $log = Audit::record('permissions.updated', $subject, ['granted' => ['users.view']], $actor);

    expect($log->fresh())
        ->actor_id->toBe($actor->id)
        ->action->toBe('permissions.updated')
        ->subject_type->toBe($subject->getMorphClass())
        ->subject_id->toBe($subject->id)
        ->meta->toBe(['granted' => ['users.view']])
        ->created_at->not->toBeNull();

    expect($log->actor->is($actor))->toBeTrue()
        ->and($log->subject->is($subject))->toBeTrue();
});

test('المنفّذ الافتراضي هو المستخدم الحالي', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor);

    expect(Audit::record('permissions.updated')->actor_id)->toBe($actor->id);
});

test('يبقى السجل بعد حذف المنفّذ', function (): void {
    User::factory()->admin()->create();
    $actor = User::factory()->admin()->create();

    Audit::record('permissions.updated', null, [], $actor);
    $actor->delete();

    expect(AuditLog::query()->sole()->actor_id)->toBe($actor->id);
});
