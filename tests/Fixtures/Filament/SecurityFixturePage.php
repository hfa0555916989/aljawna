<?php

declare(strict_types=1);

namespace Tests\Fixtures\Filament;

use App\Filament\Pages\PermissionPage;
use App\PermissionKey;

/**
 * صفحة اختبار تتطلب security.view، لإثبات آلية FR-18 قبل بناء صفحات المهام اللاحقة.
 */
class SecurityFixturePage extends PermissionPage
{
    protected static ?string $slug = 'fixture-security';

    protected static ?string $navigationLabel = 'صفحة اختبار الأمان';

    protected static ?string $title = 'صفحة اختبار الأمان';

    public static function getRequiredPermission(): PermissionKey
    {
        return PermissionKey::SecurityView;
    }
}
