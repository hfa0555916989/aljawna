<?php

declare(strict_types=1);

namespace App;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة بند في صفحة "صحة النظام" (docs/DECISIONS.md).
 */
enum HealthStatus: string implements HasColor, HasLabel
{
    case Ok = 'ok';
    case Failing = 'failing';
    case Unknown = 'unknown';
    case Unconfigured = 'unconfigured';

    public function getLabel(): string
    {
        return __('health.status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ok => 'success',
            self::Failing => 'danger',
            self::Unknown => 'warning',
            self::Unconfigured => 'gray',
        };
    }
}
