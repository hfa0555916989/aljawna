<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Filament\Panel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tests\Fixtures\Filament\OtherNumberFixturePage;
use Tests\Fixtures\Filament\SecurityFixturePage;

/**
 * يسجّل صفحات اختبار في لوحة الإدارة قبل إقلاع التطبيق، فتمرّ بتسجيل Filament
 * الطبيعي للمسارات والتنقل دون لمس إعداد اللوحة في الإنتاج.
 */
trait RegistersPermissionFixturePages
{
    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $this->traitsUsedByTest = class_uses_recursive(static::class);

        Panel::configureUsing(fn (Panel $panel): Panel => $panel->pages([
            SecurityFixturePage::class,
            OtherNumberFixturePage::class,
        ]));

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
