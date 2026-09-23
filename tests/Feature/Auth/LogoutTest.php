<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| تسجيل الخروج من اللوحة المؤقتة (T02)
|--------------------------------------------------------------------------
*/

test('اللوحة تعرض زر تسجيل الخروج', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('تسجيل الخروج')
        ->assertSee('action="'.route('logout').'"', false);
});

test('الخروج ينهي الجلسة ويوجّه إلى صفحة الدخول', function (): void {
    $this->actingAs(User::factory()->create())
        ->withSession(['marker' => 'x'])
        ->post('/logout')
        ->assertRedirect(route('login'))
        ->assertSessionMissing('marker');

    $this->assertGuest();
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('الزائر لا يستطيع طلب الخروج', function (): void {
    $this->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
});

test('الخروج لا يقبل طلب GET', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/logout')
        ->assertMethodNotAllowed();

    $this->assertAuthenticated();
});
