<?php

declare(strict_types=1);

use App\Models\Beneficiary;
use App\Models\Page;
use App\Models\User;
use App\PermissionKey;
use App\Support\TwoFactorSession;
use Database\Seeders\PermissionSeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| مصفوفة الصلاحيات لكل مسارات لوحة الإدارة (T20 — docs/SPEC.md FR-18, §12.5)
|--------------------------------------------------------------------------
| كل مسار GET تحت ADMIN_PATH مذكور هنا مع ما يتطلبه، ويفشل الاختبار إن أُضيف
| مسار جديد دون أن يُذكر. لكل مسار: الزائر إلى /login، والمبادر 404، ومشرف
| بلا الصلاحية 403، ومشرف بها فقط 200، والمدير 200، وجلسة بلا تحقق بخطوتين
| أو لم تجتز خطوته الثانية تُنهى إلى /login.
*/

const ANY_PANEL_ROLE = 'any';
const ADMIN_ONLY = 'admin';

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

/**
 * @return array<string, array{0: string, 1: Closure(): array<string, mixed>}>
 */
function panelRouteMatrix(): array
{
    $none = fn (): array => [];

    return [
        'filament.admin.pages.dashboard' => [ANY_PANEL_ROLE, $none],
        'filament.admin.pages.two-factor.recovery-codes' => [ANY_PANEL_ROLE, $none],
        'filament.admin.pages.system-health' => [ADMIN_ONLY, $none],
        'filament.admin.pages.recovery.log' => [ADMIN_ONLY, $none],
        'filament.admin.pages.recovery.stats' => [PermissionKey::StatsRecovery->value, $none],
        'filament.admin.pages.recovery' => [PermissionKey::RecoveryHandle->value, $none],
        'filament.admin.pages.security' => [PermissionKey::SecurityView->value, $none],
        'filament.admin.pages.messages' => [PermissionKey::MessagesView->value, $none],
        'filament.admin.pages.converter' => [PermissionKey::ConverterUse->value, $none],
        'filament.admin.pages.beneficiary-receipts' => [PermissionKey::TransfersView->value, $none],
        'filament.admin.resources.transfers.index' => [PermissionKey::TransfersView->value, $none],
        'filament.admin.resources.beneficiaries.index' => [PermissionKey::BeneficiariesManage->value, $none],
        'filament.admin.resources.beneficiaries.create' => [PermissionKey::BeneficiariesManage->value, $none],
        'filament.admin.resources.beneficiaries.edit' => [PermissionKey::BeneficiariesManage->value, fn (): array => ['record' => Beneficiary::factory()->create()]],
        'filament.admin.pages.content.branding' => [PermissionKey::ContentManage->value, $none],
        'filament.admin.pages.content.menus' => [PermissionKey::ContentManage->value, $none],
        'filament.admin.resources.content.pages.index' => [PermissionKey::ContentManage->value, $none],
        'filament.admin.resources.content.pages.create' => [PermissionKey::ContentManage->value, $none],
        'filament.admin.resources.content.pages.edit' => [PermissionKey::ContentManage->value, fn (): array => ['record' => Page::factory()->create()]],
        'admin.pages.preview' => [PermissionKey::ContentManage->value, fn (): array => ['page' => Page::factory()->create()]],
        'filament.admin.resources.supervisors.index' => [PermissionKey::SupervisorsManage->value, $none],
        'filament.admin.resources.supervisors.edit' => [PermissionKey::SupervisorsManage->value, fn (): array => ['record' => User::factory()->supervisor()->create()]],
    ];
}

/**
 * مشرف بالصلاحية المطلوبة واعتمادياتها فقط.
 *
 * @param  list<string>  $permissions
 */
function supervisorWith(array $permissions, bool $withTwoFactor = true): User
{
    $granted = collect($permissions)
        ->flatMap(fn (string $permission): array => [$permission, ...array_map(fn (PermissionKey $key): string => $key->value, PermissionKey::from($permission)->requires())])
        ->unique()
        ->values()
        ->all();

    $factory = User::factory()->supervisor()->withPermissions($granted);

    return ($withTwoFactor ? $factory : $factory->withoutTwoFactor())->create();
}

/**
 * @return list<string>
 */
function panelGetRouteNames(): array
{
    $prefix = trim((string) config('admin.path'), '/');

    return collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array('GET', $route->methods(), true)
            && ($route->uri() === $prefix || str_starts_with($route->uri(), $prefix.'/')))
        ->map(fn (Route $route): ?string => $route->getName())
        ->reject(fn (?string $name): bool => $name === 'admin.login.redirect')
        ->values()
        ->all();
}

test('المصفوفة تغطي كل مسارات GET تحت ADMIN_PATH، ولا تذكر مسارًا غير موجود', function (): void {
    $routes = panelGetRouteNames();
    $matrix = array_keys(panelRouteMatrix());

    expect(array_values(array_diff($routes, $matrix)))->toBe([])
        ->and(array_values(array_diff($matrix, $routes)))->toBe([]);
});

test('مصفوفة الصلاحيات', function (string $routeName): void {
    [$access, $parameters] = panelRouteMatrix()[$routeName];
    $url = route($routeName, $parameters());

    // الزائر يُعاد إلى الدخول الموحّد، والمبادر لا يعرف بوجود اللوحة.
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($url)->assertNotFound();

    // مشرف بلا أي صلاحية.
    $this->actingAs(supervisorWith([]))->get($url)->assertStatus($access === ANY_PANEL_ROLE ? 200 : 403);

    if ($access === ADMIN_ONLY) {
        // لا تكفي كل صلاحيات المشرفين مجتمعة.
        $this->actingAs(supervisorWith(PermissionKey::values()))->get($url)->assertForbidden();
    } elseif ($access !== ANY_PANEL_ROLE) {
        $this->actingAs(supervisorWith([$access]))->get($url)->assertOk();

        // كل الصلاحيات الأخرى لا تغني عن المطلوبة.
        $others = array_values(array_filter(PermissionKey::values(), fn (string $key): bool => $key !== $access
            && ! in_array($access, array_map(fn (PermissionKey $required): string => $required->value, PermissionKey::from($key)->requires()), true)));
        $this->actingAs(supervisorWith($others))->get($url)->assertForbidden();
    }

    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();

    // خطوة التحقق بخطوتين: لا وصول قبل التفعيل، ولا بجلسة لم تجتز الخطوة الثانية.
    $this->actingAs(supervisorWith($access === ANY_PANEL_ROLE || $access === ADMIN_ONLY ? [] : [$access], withTwoFactor: false))
        ->get($url)
        ->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();

    $this->actingAs(User::factory()->admin()->create())
        ->withSession([TwoFactorSession::SESSION_KEY => false])
        ->get($url)
        ->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();

    // المعطَّل لا يعرف بوجود اللوحة ولو بقيت جلسته.
    $this->flushSession();
    $this->actingAs(User::factory()->admin()->inactive()->create())->get($url)->assertNotFound();
})->with(fn (): array => array_keys(panelRouteMatrix()));

test('مسار تعديل صلاحيات المشرف (PUT) بنفس الشروط', function (): void {
    $target = User::factory()->supervisor()->create();
    $url = route('admin.supervisors.permissions.update', $target);
    $payload = ['permissions' => [PermissionKey::UsersView->value]];

    $this->put($url, $payload)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->put($url, $payload)->assertNotFound();
    $this->actingAs(supervisorWith([PermissionKey::UsersView->value]))->put($url, $payload)->assertForbidden();
    $this->actingAs(supervisorWith([PermissionKey::SupervisorsManage->value, PermissionKey::UsersView->value], withTwoFactor: false))
        ->put($url, $payload)
        ->assertRedirect(route('login'));

    expect($target->fresh()->permissions()->count())->toBe(0);

    $this->actingAs(supervisorWith([PermissionKey::SupervisorsManage->value, PermissionKey::UsersView->value]))
        ->from(adminPath('supervisors'))
        ->put($url, $payload)
        ->assertRedirect(adminPath('supervisors'))
        ->assertSessionHas('status');

    expect($target->fresh()->hasGrantedPermission(PermissionKey::UsersView->value))->toBeTrue();
});

test('المسار الافتراضي الشائع /admin لا يؤدي إلى شيء لأي دور', function (): void {
    $this->get('/admin')->assertNotFound();
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertNotFound();
});
