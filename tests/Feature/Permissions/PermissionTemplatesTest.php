<?php

declare(strict_types=1);

use App\Actions\Supervisors\UpdateSupervisorPermissions;
use App\Models\User;
use App\PermissionKey;
use App\Support\PermissionTemplates;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| قوالب الأدوار الجاهزة (T03 — docs/SPEC.md §2)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

test('القالب يمنح المجموعة الواردة في المواصفة تمامًا', function (string $template, string $label, array $expected): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['messages.view'])->create();

    app(UpdateSupervisorPermissions::class)->handle(
        User::factory()->admin()->create(),
        $supervisor,
        PermissionTemplates::permissions($template),
    );

    expect(PermissionTemplates::all()[$template]['label'])->toBe($label)
        ->and($supervisor->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect($expected)->sort()->values()->all());
})->with([
    ['transfers_reviewer', 'مراجع الحوالات', ['stats.view', 'transfers.view', 'transfers.review']],
    ['support_recovery', 'مسؤول الدعم والاستعادة', ['recovery.handle', 'users.view']],
    ['follow_up', 'مشرف متابعة', ['stats.view', 'transfers.view', 'users.view', 'converter.use']],
    ['security', 'مشرف أمان', ['security.view', 'users.suspend']],
    ['beneficiaries', 'مسؤول المستفيدين', ['beneficiaries.manage', 'stats.view', 'transfers.view']],
]);

test('القوالب خمسة، وكل مفاتيحها معروفة', function (): void {
    expect(PermissionTemplates::all())->toHaveCount(5);

    foreach (PermissionTemplates::all() as $template) {
        foreach ($template['permissions'] as $permission) {
            expect(PermissionKey::tryFrom($permission))->not->toBeNull();
        }
    }
});

test('قالب غير معروف يُرفض', function (): void {
    PermissionTemplates::permissions('super_admin');
})->throws(InvalidArgumentException::class);
