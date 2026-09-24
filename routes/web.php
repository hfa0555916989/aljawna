<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\UpdateSupervisorPermissionsController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ShowTransferReceiptController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Beneficiaries\Index as BeneficiaryIndex;
use App\Livewire\Beneficiaries\Show as BeneficiaryShow;
use App\Livewire\Dashboard;
use App\Livewire\Home;
use App\Livewire\JoinSupervisor;
use App\Livewire\Transfers\Create as TransferCreate;
use App\Livewire\Transfers\Index as TransferIndex;
use App\PermissionKey;
use Illuminate\Support\Facades\Route;

Route::livewire('/', Home::class)->name('home');
Route::livewire('/beneficiaries', BeneficiaryIndex::class)->name('beneficiaries.index');
Route::livewire('/beneficiaries/{beneficiary}', BeneficiaryShow::class)
    ->whereNumber('beneficiary')
    ->name('beneficiaries.show');

Route::livewire('/join/{token}', JoinSupervisor::class)
    ->where('token', '[A-Za-z0-9\-_]+')
    ->name('supervisors.join');

Route::middleware('guest')->group(function (): void {
    Route::livewire('/register', Register::class)->name('register');
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/forgot-password', ForgotPassword::class)->name('password.forgot');
    Route::livewire('/reset/{token}', ResetPassword::class)
        ->where('token', '[A-Za-z0-9\-_]+')
        ->name('password.reset');
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

    Route::put('/admin/supervisors/{supervisor}/permissions', UpdateSupervisorPermissionsController::class)
        ->whereNumber('supervisor')
        ->middleware('can:'.PermissionKey::SupervisorsManage->value)
        ->name('admin.supervisors.permissions.update');
});
