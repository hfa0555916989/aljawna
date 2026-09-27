<?php

declare(strict_types=1);

use App\Filament\Pages\RecoveryStats;
use App\Models\PasswordResetRequest;
use App\Models\PasswordResetToken;
use App\Models\RecoveryLog;
use App\Models\User;
use App\PasswordResetStatus;
use App\RecoveryLogAction;
use App\Services\RecoveryStatsService;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| إحصائيات الاستعادة (T13 — docs/SPEC.md §4.4, FR-42)
|--------------------------------------------------------------------------
| الوصول: المدير أو من يملك stats.recovery. الحدود الزمنية بتوقيت الرياض،
| والأرقام مصدرها recovery_logs (الروابط وتعديلات الأرقام) وpassword_reset_tokens
| (الاستعادات المنجزة فعليًا، منسوبة إلى المشرف الذي أصدر الرابط المُستخدَم).
*/

const STATS_PAGE = '/admin/recovery/stats';

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel('admin');
});

function statsRequest(?User $owner = null): PasswordResetRequest
{
    return PasswordResetRequest::query()->create([
        'user_id' => ($owner ?? User::factory()->create())->id,
        'status' => PasswordResetStatus::LinkSent,
        'requested_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
    ]);
}

function statsLog(PasswordResetRequest $request, User $performer, RecoveryLogAction $action): RecoveryLog
{
    return RecoveryLog::query()->create([
        'request_id' => $request->id,
        'user_id' => $request->user_id,
        'performed_by' => $performer->id,
        'action' => $action,
        'sent_to_phone' => $action === RecoveryLogAction::PhoneChanged ? null : '+966500000000',
        'old_phone' => $action === RecoveryLogAction::PhoneChanged ? '+966500000001' : null,
        'new_phone' => $action === RecoveryLogAction::PhoneChanged ? '+966500000002' : null,
    ]);
}

function statsToken(PasswordResetRequest $request, User $issuer, CarbonImmutable $usedAt): PasswordResetToken
{
    return PasswordResetToken::query()->create([
        'request_id' => $request->id,
        'issued_by' => $issuer->id,
        'token_hash' => hash('sha256', random_bytes(16)),
        'sent_to_phone' => '+966500000000',
        'is_other_number' => false,
        'expires_at' => $usedAt->addMinutes(30),
        'used_at' => $usedAt,
    ]);
}

test('من لا يملك stats.recovery يُرفض بـ 403', function (): void {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)->get(STATS_PAGE)->assertForbidden();
});

test('المشرف الممنوح stats.recovery مباشرة يفتح الصفحة', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['stats.recovery'])->create();

    $this->actingAs($supervisor)->get(STATS_PAGE)->assertOk();
});

test('المدير يفتح الصفحة ضمنيًا دون منح مباشر', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(STATS_PAGE)->assertOk();

    expect(RecoveryStats::canAccess())->toBeTrue();
});

test('سحب stats.recovery يمنع الصفحة فورًا', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['stats.recovery'])->create();

    $this->actingAs($supervisor)->get(STATS_PAGE)->assertOk();

    $supervisor->revokePermissionTo('stats.recovery');

    $this->actingAs($supervisor->fresh())->get(STATS_PAGE)->assertForbidden();
});

test('المبادر لا يصل إلى الصفحة', function (): void {
    $this->actingAs(User::factory()->create())->get(STATS_PAGE)->assertRedirect(route('dashboard'));
});

test('حد بداية ونهاية اليوم بتوقيت الرياض دقيق عند منتصف الليل', function (): void {
    $supervisor = User::factory()->supervisor()->create();
    $request = statsRequest();

    $this->travelTo(CarbonImmutable::parse('2026-09-24 23:59:59', 'Asia/Riyadh'));
    statsLog($request, $supervisor, RecoveryLogAction::LinkRegistered);

    $this->travelTo(CarbonImmutable::parse('2026-09-25 00:00:00', 'Asia/Riyadh'));
    statsLog($request, $supervisor, RecoveryLogAction::LinkRegistered);

    $service = app(RecoveryStatsService::class);

    [$start24, $end24] = $service->boundsFor('day', day: CarbonImmutable::parse('2026-09-24', 'Asia/Riyadh'));
    [$start25, $end25] = $service->boundsFor('day', day: CarbonImmutable::parse('2026-09-25', 'Asia/Riyadh'));

    expect($service->summarize($start24, $end24)['totals']['links_issued'])->toBe(1)
        ->and($service->summarize($start25, $end25)['totals']['links_issued'])->toBe(1);
});

test('حد بداية ونهاية الشهر بتوقيت الرياض دقيق', function (): void {
    $supervisor = User::factory()->supervisor()->create();
    $request = statsRequest();

    $this->travelTo(CarbonImmutable::parse('2026-08-31 23:59:59', 'Asia/Riyadh'));
    statsLog($request, $supervisor, RecoveryLogAction::PhoneChanged);

    $this->travelTo(CarbonImmutable::parse('2026-09-01 00:00:00', 'Asia/Riyadh'));
    statsLog($request, $supervisor, RecoveryLogAction::PhoneChanged);

    $service = app(RecoveryStatsService::class);

    [$startAug, $endAug] = $service->boundsFor('month', month: CarbonImmutable::parse('2026-08-01', 'Asia/Riyadh'));
    [$startSep, $endSep] = $service->boundsFor('month', month: CarbonImmutable::parse('2026-09-01', 'Asia/Riyadh'));

    expect($service->summarize($startAug, $endAug)['totals']['phone_changed'])->toBe(1)
        ->and($service->summarize($startSep, $endSep)['totals']['phone_changed'])->toBe(1);
});

test('حد بداية ونهاية السنة بتوقيت الرياض دقيق', function (): void {
    $supervisor = User::factory()->supervisor()->create();
    $request = statsRequest();

    $this->travelTo(CarbonImmutable::parse('2026-12-31 23:59:59', 'Asia/Riyadh'));
    statsLog($request, $supervisor, RecoveryLogAction::LinkOther);

    $this->travelTo(CarbonImmutable::parse('2027-01-01 00:00:00', 'Asia/Riyadh'));
    statsLog($request, $supervisor, RecoveryLogAction::LinkOther);

    $service = app(RecoveryStatsService::class);

    [$start2026, $end2026] = $service->boundsFor('year', year: 2026);
    [$start2027, $end2027] = $service->boundsFor('year', year: 2027);

    expect($service->summarize($start2026, $end2026)['totals']['links_issued'])->toBe(1)
        ->and($service->summarize($start2027, $end2027)['totals']['links_issued'])->toBe(1);
});

test('الفترة المخصصة تشمل يوم البداية ويوم النهاية كاملين وتستثني ما قبلهما وما بعدهما', function (): void {
    $supervisor = User::factory()->supervisor()->create();
    $request = statsRequest();

    $this->travelTo(CarbonImmutable::parse('2026-09-09 23:59:59', 'Asia/Riyadh'));
    statsLog($request, $supervisor, RecoveryLogAction::LinkRegistered); // قبل الفترة بثانية

    $this->travelTo(CarbonImmutable::parse('2026-09-10 00:00:00', 'Asia/Riyadh'));
    statsLog($request, $supervisor, RecoveryLogAction::LinkRegistered); // أول لحظة في الفترة

    $this->travelTo(CarbonImmutable::parse('2026-09-12 23:59:59', 'Asia/Riyadh'));
    statsLog($request, $supervisor, RecoveryLogAction::LinkRegistered); // آخر لحظة في الفترة

    $this->travelTo(CarbonImmutable::parse('2026-09-13 00:00:00', 'Asia/Riyadh'));
    statsLog($request, $supervisor, RecoveryLogAction::LinkRegistered); // بعد الفترة

    $service = app(RecoveryStatsService::class);

    [$start, $end] = $service->boundsFor(
        'custom',
        start: CarbonImmutable::parse('2026-09-10', 'Asia/Riyadh'),
        end: CarbonImmutable::parse('2026-09-12', 'Asia/Riyadh'),
    );

    expect($service->summarize($start, $end)['totals']['links_issued'])->toBe(2);
});

test('تبديل تاريخي الفترة المخصصة تلقائيًا إن كانت النهاية قبل البداية', function (): void {
    $service = app(RecoveryStatsService::class);

    [$start, $end] = $service->boundsFor(
        'custom',
        start: CarbonImmutable::parse('2026-09-12', 'Asia/Riyadh'),
        end: CarbonImmutable::parse('2026-09-10', 'Asia/Riyadh'),
    );

    expect($start->toDateString())->toBe('2026-09-10')
        ->and($end->toDateString())->toBe('2026-09-13');
});

test('الأرقام تطابق recovery_log وتُنسب الاستعادة المنجزة لمن أصدر رابطها المستخدَم، بتفصيل حسب المشرف', function (): void {
    $supervisorA = User::factory()->supervisor()->withPermissions(['stats.recovery'])->create(['full_name' => 'المشرف الأول العجاوني الكريم']);
    $supervisorB = User::factory()->supervisor()->create(['full_name' => 'المشرف الثاني العجاوني الكريم']);

    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00:00', 'Asia/Riyadh'));

    $requestA = statsRequest();
    statsLog($requestA, $supervisorA, RecoveryLogAction::LinkRegistered);
    statsLog($requestA, $supervisorA, RecoveryLogAction::LinkOther);
    statsToken($requestA, $supervisorA, CarbonImmutable::parse('2026-09-15 11:00:00', 'Asia/Riyadh'));

    $requestB = statsRequest();
    statsLog($requestB, $supervisorB, RecoveryLogAction::PhoneChanged);

    // خارج الفترة عمدًا: لا يجب أن يُحتسب ليوم 2026-09-15.
    $this->travelTo(CarbonImmutable::parse('2026-09-16 00:00:00', 'Asia/Riyadh'));
    statsLog(statsRequest(), $supervisorA, RecoveryLogAction::LinkRegistered);

    $expectedLogCount = RecoveryLog::query()
        ->where('created_at', '>=', CarbonImmutable::parse('2026-09-15', 'Asia/Riyadh'))
        ->where('created_at', '<', CarbonImmutable::parse('2026-09-16', 'Asia/Riyadh'))
        ->count();

    expect($expectedLogCount)->toBe(3);

    $service = app(RecoveryStatsService::class);
    [$start, $end] = $service->boundsFor('day', day: CarbonImmutable::parse('2026-09-15', 'Asia/Riyadh'));
    $summary = $service->summarize($start, $end);

    expect($summary['totals'])->toBe([
        'completed' => 1,
        'links_issued' => 2,
        'phone_changed' => 1,
    ])
        ->and(array_sum(array_column($summary['by_supervisor'], 'link_registered'))
            + array_sum(array_column($summary['by_supervisor'], 'link_other'))
            + array_sum(array_column($summary['by_supervisor'], 'phone_changed')))
        ->toBe($expectedLogCount);

    $rowA = collect($summary['by_supervisor'])->firstWhere('id', $supervisorA->id);
    $rowB = collect($summary['by_supervisor'])->firstWhere('id', $supervisorB->id);

    expect($rowA)->not->toBeNull()
        ->and($rowA['link_registered'])->toBe(1)
        ->and($rowA['link_other'])->toBe(1)
        ->and($rowA['completed'])->toBe(1)
        ->and($rowA['total'])->toBe(3)
        ->and($rowB)->not->toBeNull()
        ->and($rowB['phone_changed'])->toBe(1)
        ->and($rowB['total'])->toBe(1);

    Livewire::actingAs($supervisorA)
        ->test(RecoveryStats::class)
        ->set('period', 'day')
        ->set('date', '2026-09-15')
        ->assertSee('المشرف الأول العجاوني الكريم')
        ->assertSee('المشرف الثاني العجاوني الكريم')
        ->assertSeeHtml('data-recovery-stats-total="completed">1<')
        ->assertSeeHtml('data-recovery-stats-total="links_issued">2<')
        ->assertSeeHtml('data-recovery-stats-total="phone_changed">1<');
});
