<?php

declare(strict_types=1);

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * صورة رمزية محلية (SVG مضمّن) بالحرف الأول من الاسم، بدل ui-avatars.com
 * الذي يرسل الاسم إلى خدمة خارجية.
 */
class InitialAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $name = trim(Filament::getNameForDefaultAvatar($record));
        $initial = htmlspecialchars(mb_substr($name, 0, 1), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#0F4C45"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#FFFFFF" '
            .'font-family="IBM Plex Sans Arabic, Tahoma, sans-serif" font-size="30" font-weight="600">'
            .$initial.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
