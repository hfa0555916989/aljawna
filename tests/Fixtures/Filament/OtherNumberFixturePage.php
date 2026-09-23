<?php

declare(strict_types=1);

namespace Tests\Fixtures\Filament;

use App\Filament\Pages\PermissionPage;
use App\PermissionKey;

/**
 * صفحة اختبار تتطلب recovery.other_number، وهي تعتمد على recovery.handle.
 */
class OtherNumberFixturePage extends PermissionPage
{
    protected static ?string $slug = 'fixture-other-number';

    protected static ?string $navigationLabel = 'صفحة اختبار الرقم المختلف';

    protected static ?string $title = 'صفحة اختبار الرقم المختلف';

    public static function getRequiredPermission(): PermissionKey
    {
        return PermissionKey::RecoveryOtherNumber;
    }
}
