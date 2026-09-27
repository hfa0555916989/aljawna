<?php

declare(strict_types=1);

use App\Filament\Pages\Branding;
use App\Models\AuditLog;
use App\Models\SiteBranding;
use App\Models\User;
use App\Services\BrandingCss;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| صفحة الهوية (T16 — docs/SPEC.md FR-49, FR-56, §12.13)
|--------------------------------------------------------------------------
*/

const BRANDING_PAGE = 'content/branding';

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Storage::fake('public');
});

test('من لا يملك content.manage يُرفض بـ 403', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['users.view'])->create();

    $this->actingAs($supervisor)->get(adminPath(BRANDING_PAGE))->assertForbidden();

    expect(Branding::canAccess())->toBeFalse();
});

test('الممنوح content.manage يفتح الصفحة', function (): void {
    $this->actingAs(contentManager())->get(adminPath(BRANDING_PAGE))->assertOk()->assertSee(__('branding.navigation'));
});

test('المدير يفتح الصفحة ضمنيًا', function (): void {
    $this->actingAs(User::factory()->admin()->create())->get(adminPath(BRANDING_PAGE))->assertOk();
});

test('المبادر لا يصل إلى الصفحة (404)', function (): void {
    $this->actingAs(User::factory()->create())->get(adminPath(BRANDING_PAGE))->assertNotFound();
});

test('حفظ الاسم واللونين الصحيحين ينجح ويُسجَّل في التدقيق ويُبطل كاش CSS', function (): void {
    $manager = contentManager();

    // يبني الكاش أولًا بالقيم الافتراضية، فيثبت أنه فُرِّغ بعد الحفظ.
    $before = BrandingCss::variables();
    expect($before)->toContain('#1F2A44');

    Livewire::actingAs($manager)
        ->test(Branding::class)
        ->set('initiativeName', 'مبادرة العجاونة الخيرية')
        ->set('primaryColorKey', 'forest')
        ->set('secondaryColorKey', 'brass')
        ->call('save')
        ->assertHasNoErrors();

    $branding = SiteBranding::current();

    expect($branding->initiative_name)->toBe('مبادرة العجاونة الخيرية')
        ->and($branding->primary_color_key)->toBe('forest')
        ->and($branding->secondary_color_key)->toBe('brass')
        ->and($branding->updated_by)->toBe($manager->id);

    $audit = AuditLog::query()->where('action', Branding::AUDIT_UPDATED)->sole();

    expect($audit->actor_id)->toBe($manager->id)
        ->and($audit->subject_id)->toBe($branding->id)
        ->and($audit->meta)->toHaveKeys(['initiative_name', 'primary_color_key', 'secondary_color_key']);

    // الكاش أُبطل تلقائيًا، فتُبنى قيم جديدة تطابق اللونين الجديدين لا القديمين.
    $after = BrandingCss::variables();
    expect($after)->not->toBe($before)
        ->and($after)->toContain('#0F4C45')
        ->and($after)->toContain('#B58A2A');
});

test('لون غير معرَّف في اللوحة يُرفض ولا يُحفظ', function (): void {
    Livewire::actingAs(contentManager())
        ->test(Branding::class)
        ->set('primaryColorKey', 'not-a-real-key')
        ->call('save')
        ->assertHasErrors(['primaryColorKey']);

    expect(SiteBranding::current()->primary_color_key)->toBe('navy');
});

test('لون ضعيف التباين مُصنَّع للاختبار يُرفض ولا يُحفظ', function (): void {
    config(['branding.palette.primary.weak' => [
        'label' => 'ضعيف (للاختبار)',
        'light' => '#F5F5F0',
        'dark' => '#101010',
    ]]);

    Livewire::actingAs(contentManager())
        ->test(Branding::class)
        ->set('primaryColorKey', 'weak')
        ->call('save')
        ->assertHasErrors(['primaryColorKey'])
        ->assertSee(__('branding.validation.color_contrast'));

    expect(SiteBranding::current()->primary_color_key)->toBe('navy');
});

test('رفع ملف بامتداد مزيَّف (نص عادي بامتداد png) يُرفض', function (): void {
    $fake = UploadedFile::fake()->createWithContent('logo.png', 'ليست صورة على الإطلاق');

    Livewire::actingAs(contentManager())
        ->test(Branding::class)
        ->set('logoLight', $fake)
        ->call('save')
        ->assertHasErrors(['logoLight']);

    expect(SiteBranding::current()->logo_light_path)->toBeNull();
});

test('رفع صورة PNG صحيحة يمر بإعادة الترميز ويُحفظ على قرص عام باسم عشوائي', function (): void {
    $original = pngBytes(textChunk: 'SECRET-BRAND-META');
    $upload = UploadedFile::fake()->createWithContent('new-logo.png', $original);

    Livewire::actingAs(contentManager())
        ->test(Branding::class)
        ->set('logoLight', $upload)
        ->call('save')
        ->assertHasNoErrors();

    $branding = SiteBranding::current();

    expect($branding->logo_light_path)->not->toBeNull()
        ->and($branding->logo_light_path)->toMatch('/^[A-Za-z0-9]{40}\.png$/');

    $stored = (string) Storage::disk('public')->get($branding->logo_light_path);

    expect($stored)->not->toBe($original)
        ->and($stored)->not->toContain('SECRET-BRAND-META')
        ->and(getimagesizefromstring($stored)['mime'])->toBe('image/png');

    // يُخدم من نطاق الموقع نفسه عبر قرص public، لا رابط خارجي.
    expect($branding->lightLogoUrl())->toContain('/storage/');
});

test('لا شعار مرفوع يعني استخدام الشعار الافتراضي المعتمد عبر مسار brand.asset', function (): void {
    $branding = SiteBranding::current();

    expect($branding->lightLogoUrl())->toBe(route('brand.asset', ['asset' => SiteBranding::DEFAULT_LIGHT_LOGO_ASSET]))
        ->and($branding->darkLogoUrl())->toBe(route('brand.asset', ['asset' => SiteBranding::DEFAULT_DARK_LOGO_ASSET]))
        ->and($branding->iconUrl())->toBe(route('brand.asset', ['asset' => SiteBranding::DEFAULT_ICON_ASSET]));
});

test('مسار brand.asset يقدّم SVG المعتمد فعليًا من resources/images/brand', function (): void {
    $this->get(route('brand.asset', ['asset' => SiteBranding::DEFAULT_ICON_ASSET]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertSee('<svg', false);
});

test('مسار brand.asset يرفض اسم ملف غير معتمد بـ 404', function (): void {
    $this->get('/brand/not-an-allowed-file.svg')->assertNotFound();
});
