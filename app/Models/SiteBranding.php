<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\BrandingCss;
use App\Support\BrandColorPalette;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * سجل هوية الموقع الوحيد (docs/SPEC.md FR-49): الاسم والشعار والأيقونة ولونان.
 *
 * صف واحد فقط (id = 1)، يُنشأ تلقائيًا بقيم افتراضية عند أول استدعاء لـ current().
 * الشعار/الأيقونة: null يعني استخدام ملفات SVG المعتمدة في resources/images/brand
 * (مُقدَّمة عبر مسار brand.asset)، وقيمة غير null تعني ملفًا مرفوعًا على قرص public
 * (App\Services\BrandingImageStorage) بعد فحص نوع فعلي وإعادة ترميز (§12.13).
 */
#[Fillable([
    'initiative_name',
    'logo_light_path',
    'logo_dark_path',
    'icon_path',
    'primary_color_key',
    'secondary_color_key',
    'updated_by',
])]
class SiteBranding extends Model
{
    protected $table = 'site_branding';

    public const int SINGLETON_ID = 1;

    public const string DEFAULT_LIGHT_LOGO_ASSET = 'ajawna-wordmark-light-transparent.svg';

    public const string DEFAULT_DARK_LOGO_ASSET = 'ajawna-wordmark-dark-transparent.svg';

    public const string DEFAULT_ICON_ASSET = 'ajawna-mark.svg';

    /**
     * سجل الهوية الحالي، يُنشأ بالقيم الافتراضية إن لم يوجد.
     *
     * id ليس Fillable (مقصود؛ لا يُعدَّل بعد الإنشاء)، فتُستخدم forceFill هنا
     * فقط لتثبيته على SINGLETON_ID عند إنشاء أول سجل، بخلاف firstOrCreate
     * العادية التي تتجاهل id بصمت لأنه غير قابل للتعبئة الجماعية فتنشئ صفًّا
     * جديدًا بمعرّف تلقائي مختلف في كل استدعاء.
     */
    public static function current(): self
    {
        $branding = static::query()->find(self::SINGLETON_ID);

        if ($branding instanceof self) {
            return $branding;
        }

        $branding = new self;
        $branding->forceFill([
            'id' => self::SINGLETON_ID,
            'initiative_name' => config('app.name'),
            'primary_color_key' => config('branding.default_primary'),
            'secondary_color_key' => config('branding.default_secondary'),
        ]);

        try {
            $branding->save();
        } catch (QueryException) {
            // سباق نادر: طلب آخر أنشأ الصف في اللحظة نفسها.
            return static::query()->findOrFail(self::SINGLETON_ID);
        }

        return $branding;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lightLogoUrl(): string
    {
        return $this->logo_light_path !== null
            ? Storage::disk('public')->url($this->logo_light_path)
            : URL::route('brand.asset', ['asset' => self::DEFAULT_LIGHT_LOGO_ASSET]);
    }

    public function darkLogoUrl(): string
    {
        return $this->logo_dark_path !== null
            ? Storage::disk('public')->url($this->logo_dark_path)
            : URL::route('brand.asset', ['asset' => self::DEFAULT_DARK_LOGO_ASSET]);
    }

    public function iconUrl(): string
    {
        return $this->icon_path !== null
            ? Storage::disk('public')->url($this->icon_path)
            : URL::route('brand.asset', ['asset' => self::DEFAULT_ICON_ASSET]);
    }

    /**
     * @return array{label: string, light: string, dark: string}
     */
    public function primaryColor(): array
    {
        return BrandColorPalette::find(BrandColorPalette::ROLE_PRIMARY, $this->primary_color_key)
            ?? BrandColorPalette::defaultEntry(BrandColorPalette::ROLE_PRIMARY);
    }

    /**
     * @return array{label: string, light: string, dark: string}
     */
    public function secondaryColor(): array
    {
        return BrandColorPalette::find(BrandColorPalette::ROLE_SECONDARY, $this->secondary_color_key)
            ?? BrandColorPalette::defaultEntry(BrandColorPalette::ROLE_SECONDARY);
    }

    /**
     * يُبطل كاش متغيرات CSS عند أي تعديل على الهوية (tasks/T16-branding.md #4).
     */
    protected static function booted(): void
    {
        static::saved(function (): void {
            BrandingCss::forget();
        });
    }
}
