<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\User;
use App\Support\ReservedSlugs;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\BootsWithCustomAdminPath;

/*
|--------------------------------------------------------------------------
| مسار اللوحة المتغير من ADMIN_PATH (docs/DECISIONS.md، T20)
|--------------------------------------------------------------------------
| كل مسارات الإدارة (Filament وخارجها) تنتقل مع المتغير، ولا يبقى منها شيء على
| المسار الافتراضي ولا على /admin، ويُحجز المسار الجديد في منشئ الصفحات.
*/

uses(BootsWithCustomAdminPath::class);

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

afterEach(function (): void {
    $this->restoreAdminPath();
});

test('اللوحة ومساراتها خارج Filament على المسار المخصَّص', function (): void {
    $custom = $this::CUSTOM_ADMIN_PATH;
    $page = Page::factory()->create();

    expect(config('admin.path'))->toBe($custom)
        ->and(route('filament.admin.pages.dashboard', [], false))->toBe('/'.$custom)
        ->and(route('filament.admin.pages.system-health', [], false))->toBe('/'.$custom.'/system-health')
        ->and(route('admin.pages.preview', $page, false))->toStartWith('/'.$custom.'/')
        ->and(route('admin.supervisors.permissions.update', User::factory()->supervisor()->create(), false))->toStartWith('/'.$custom.'/');

    $this->get('/'.$custom)->assertRedirect(route('login'));
    $this->get('/'.$custom.'/login')->assertRedirect('/login');

    $this->actingAs(User::factory()->admin()->create())->get('/'.$custom.'/system-health')->assertOk();
    $this->actingAs(User::factory()->create())->get('/'.$custom)->assertNotFound();
});

test('لا شيء على المسار الافتراضي ولا على /admin بعد تغييره', function (): void {
    $admin = User::factory()->admin()->create();

    foreach (['/hq-7r3m9k', '/hq-7r3m9k/system-health', '/hq-7r3m9k/messages', '/admin', '/admin/system-health'] as $path) {
        $this->actingAs($admin)->get($path)->assertNotFound();
    }
});

test('المسار المخصَّص محجوز في منشئ الصفحات', function (): void {
    expect(ReservedSlugs::isReserved($this::CUSTOM_ADMIN_PATH))->toBeTrue()
        ->and(ReservedSlugs::isReserved('admin'))->toBeTrue();
});

test('الدخول الموحّد يعيد دور اللوحة إلى الصفحة المقصودة تحت المسار المخصَّص', function (): void {
    $this->get('/'.$this::CUSTOM_ADMIN_PATH.'/messages')->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(url('/'.$this::CUSTOM_ADMIN_PATH.'/messages'));
});
