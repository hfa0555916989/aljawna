<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
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
});
