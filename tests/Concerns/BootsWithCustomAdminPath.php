<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

/**
 * يُقلع التطبيق بمسار لوحة مخصَّص من ADMIN_PATH، كما يُضبط في بيئة الإنتاج، ثم
 * يعيد البيئة كما كانت بعد كل اختبار. المسار يُقرأ عند الإقلاع (config/admin.php)، فلا يكفي
 * تغيير الإعداد بعده.
 */
trait BootsWithCustomAdminPath
{
    public const string CUSTOM_ADMIN_PATH = 'ops-5x2q8w';

    public function createApplication(): Application
    {
        $_ENV['ADMIN_PATH'] = $_SERVER['ADMIN_PATH'] = self::CUSTOM_ADMIN_PATH;
        putenv('ADMIN_PATH='.self::CUSTOM_ADMIN_PATH);

        /** @var Application $app */
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $this->traitsUsedByTest = class_uses_recursive(static::class);

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * يُستدعى من afterEach في ملف الاختبار (Pest يحجز tearDown).
     */
    public static function restoreAdminPath(): void
    {
        unset($_ENV['ADMIN_PATH'], $_SERVER['ADMIN_PATH']);
        putenv('ADMIN_PATH');
    }
}
