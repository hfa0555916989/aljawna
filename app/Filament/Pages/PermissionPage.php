<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\PermissionKey;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

/**
 * أساس صفحات اللوحة المرتبطة بمفتاح صلاحية (FR-18): يُخفى عنصر التنقل
 * عمّن لا يملكها، ويُرفض الوصول المباشر بالرابط بـ 403.
 */
abstract class PermissionPage extends Page
{
    abstract public static function getRequiredPermission(): PermissionKey;

    public static function canAccess(): bool
    {
        return Gate::allows(static::getRequiredPermission()->value);
    }
}
