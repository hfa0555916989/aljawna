<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\PermissionKey;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

/**
 * أداة "نظرة عامة" المؤقتة في لوحة الإدارة، وتُملأ بالمؤشرات في T08.
 */
class AdminOverview extends Widget
{
    protected static ?int $sort = -1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.admin-overview';

    public static function canView(): bool
    {
        return Gate::allows(PermissionKey::StatsView->value);
    }
}
