<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\UpdateSupervisorPermissionsController;
use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\PermissionKey;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::livewire('/register', Register::class)->name('register');
    Route::livewire('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::put('/admin/supervisors/{supervisor}/permissions', UpdateSupervisorPermissionsController::class)
        ->whereNumber('supervisor')
        ->middleware('can:'.PermissionKey::SupervisorsManage->value)
        ->name('admin.supervisors.permissions.update');
});
