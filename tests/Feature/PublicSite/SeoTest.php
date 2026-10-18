<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| الفهرسة في محركات البحث: robots.txt وnoindex وcanonical وتحقق Google (docs/SPEC.md FR-55)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    config([
        'app.url' => 'https://ajawna.com',
        'services.google.site_verification' => 'google-token-123',
    ]);
});

test('robots.txt على النطاق الأساسي يسمح بالزحف ويشير إلى خريطة الموقع', function (): void {
    $this->get('https://ajawna.com/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee("User-agent: *\nDisallow:\n", false)
        ->assertSee('Sitemap: https://ajawna.com/sitemap.xml', false)
        ->assertDontSee('Disallow: /', false);
});

test('robots.txt على نطاق غير أساسي يمنع الزحف كله', function (): void {
    $this->get('https://ajawna.laravel.cloud/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /', false)
        ->assertDontSee('Sitemap:', false);
});

test('الصفحات العامة على النطاق الأساسي قابلة للفهرسة وتحمل رمز تحقق Google', function (): void {
    $this->get('https://ajawna.com/')
        ->assertOk()
        ->assertSee('<meta name="google-site-verification" content="google-token-123">', false)
        ->assertSee('<link rel="canonical" href="https://ajawna.com">', false)
        ->assertSee('"@type":"WebSite"', false)
        ->assertDontSee('noindex', false);

    $this->get('https://ajawna.com/beneficiaries?tab=closed')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://ajawna.com/beneficiaries">', false)
        ->assertDontSee('noindex', false)
        ->assertDontSee('application/ld+json', false);
});

test('صفحات الدخول والتسجيل لا تُفهرس', function (string $path): void {
    $this->get('https://ajawna.com'.$path)
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
})->with(['/login', '/register', '/forgot-password']);

test('النطاق غير الأساسي لا يُفهرس، وcanonical يشير إلى النطاق الأساسي', function (): void {
    $this->get('https://ajawna.laravel.cloud/beneficiaries')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('<link rel="canonical" href="https://ajawna.com/beneficiaries">', false);
});

test('رمز تحقق Google الفارغ لا يُطبع', function (): void {
    config(['services.google.site_verification' => '']);

    $this->get('https://ajawna.com/')
        ->assertOk()
        ->assertDontSee('google-site-verification', false);
});
