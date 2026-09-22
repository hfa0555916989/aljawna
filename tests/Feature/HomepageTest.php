<?php

declare(strict_types=1);

test('الصفحة الرئيسية تُعرض بنجاح', function (): void {
    $response = $this->get('/');

    $response->assertOk();
});

test('الصفحة الرئيسية تُعرض باتجاه RTL وبلغة عربية', function (): void {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('dir="rtl"', false);
    $response->assertSee('lang="ar"', false);
});
