<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\PreviewPageController;
use App\Http\Controllers\Admin\UpdateSupervisorPermissionsController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BrandAssetController;
use App\Http\Controllers\ShowPageController;
use App\Http\Controllers\ShowTransferReceiptController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\EnsureTwoFactorAuthentication;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetAdminPassword;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\SetUpTwoFactor;
use App\Livewire\Beneficiaries\Index as BeneficiaryIndex;
use App\Livewire\Beneficiaries\Show as BeneficiaryShow;
use App\Livewire\Dashboard;
use App\Livewire\Home;
use App\Livewire\JoinAdmin;
use App\Livewire\JoinSupervisor;
use App\Livewire\Transfers\Create as TransferCreate;
use App\Livewire\Transfers\Index as TransferIndex;
use App\PermissionKey;
use App\Support\ReservedSlugs;
use Illuminate\Support\Facades\Route;

Route::get('/brand/{asset}', BrandAssetController::class)
    ->where('asset', '[A-Za-z0-9\-.]+')
    ->name('brand.asset');

Route::livewire('/', Home::class)->name('home');
Route::livewire('/beneficiaries', BeneficiaryIndex::class)->name('beneficiaries.index');
Route::livewire('/beneficiaries/{beneficiary}', BeneficiaryShow::class)
    ->whereNumber('beneficiary')
    ->name('beneficiaries.show');

Route::livewire('/join/{token}', JoinSupervisor::class)
    ->where('token', '[A-Za-z0-9\-_]+')
    ->name('supervisors.join');

// إكمال حساب مدير من رابط php artisan admin:invite. لا رابط لهذه الصفحة في أي
// قائمة أو لوحة (docs/SPEC.md §2)، فمن يصلها يحتاج الرابط نفسه.
Route::livewire('/admin-join/{token}', JoinAdmin::class)
    ->where('token', '[A-Za-z0-9\-_]+')
    ->name('admin.join');

// إعداد التحقق بخطوتين من رابط php artisan admin:reset-2fa فقط، ولا رابط له في أي صفحة.
Route::livewire('/two-factor/setup/{token}', SetUpTwoFactor::class)
    ->where('token', '[A-Za-z0-9\-_]+')
    ->name('two-factor.setup');

Route::middleware('guest')->group(function (): void {
    Route::livewire('/register', Register::class)->name('register');
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/forgot-password', ForgotPassword::class)->name('password.forgot');
    Route::livewire('/reset/{token}', ResetPassword::class)
        ->where('token', '[A-Za-z0-9\-_]+')
        ->name('password.reset');

    // رابط طوارئ من php artisan admin:reset-link فقط، ولا رابط له في أي صفحة.
    Route::livewire('/admin-reset/{token}', ResetAdminPassword::class)
        ->where('token', '[A-Za-z0-9\-_]+')
        ->name('admin.password.reset');
});

Route::middleware('auth')->group(function (): void {
    Route::livewire('/dashboard', Dashboard::class)->name('dashboard');
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::livewire('/transfers', TransferCreate::class)->name('transfers.create');
    Route::livewire('/my-transfers', TransferIndex::class)->name('transfers.index');
    Route::get('/receipts/{transfer}', ShowTransferReceiptController::class)
        ->whereNumber('transfer')
        ->middleware('signed')
        ->name('transfers.receipt');

});

// مسارات إدارة خارج Filament تحت مسار اللوحة نفسه (ADMIN_PATH)، وبنفس شروطها:
// 404 لمن لا يملك دور اللوحة، والتحقق بخطوتين إلزامي (docs/DECISIONS.md).
Route::prefix((string) config('admin.path'))
    ->middleware(['auth', EnsureTwoFactorAuthentication::class])
    ->group(function (): void {
        Route::put('/supervisors/{supervisor}/permissions', UpdateSupervisorPermissionsController::class)
            ->whereNumber('supervisor')
            ->middleware('can:'.PermissionKey::SupervisorsManage->value)
            ->name('admin.supervisors.permissions.update');

        Route::get('/content/pages/{page}/preview', PreviewPageController::class)
            ->whereNumber('page')
            ->middleware('can:'.PermissionKey::ContentManage->value)
            ->name('admin.pages.preview');
    });

// صفحة دخول Filament أُلغيت: الدخول موحّد عبر /login، والرابط القديم يُحوَّل إليه.
Route::redirect('/'.config('admin.path').'/login', '/login')->name('admin.login.redirect');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// صفحات منشئ الصفحات: آخر مسار دائمًا (fallback)، فلا تحجب صفحةٌ مسارًا نظاميًا (FR-55).
Route::get('/{slug}', ShowPageController::class)
    ->where('slug', ReservedSlugs::routePattern())
    ->fallback()
    ->name('pages.show');
