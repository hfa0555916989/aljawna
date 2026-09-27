<?php

declare(strict_types=1);

namespace App;

use Filament\Support\Contracts\HasLabel;

/**
 * موضع عنصر القائمة (docs/SPEC.md §9 menu_items، FR-54).
 */
enum MenuLocation: string implements HasLabel
{
    case Header = 'header';
    case Footer = 'footer';

    public function getLabel(): string
    {
        return __('pages.menus.locations.'.$this->value);
    }
}
