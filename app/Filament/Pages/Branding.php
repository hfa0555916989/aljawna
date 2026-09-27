<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\SiteBranding;
use App\Models\User;
use App\PermissionKey;
use App\Rules\AccessibleBrandColor;
use App\Rules\BrandingImageFile;
use App\Services\Audit;
use App\Services\BrandingImageStorage;
use App\Support\BrandColorPalette;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * صفحة الهوية: الاسم والشعار (فاتح/داكن) والأيقونة ولونان (docs/SPEC.md FR-49, FR-56).
 *
 * الصلاحية content.manage. لا يمكن حفظ لون ضعيف التباين (App\Rules\AccessibleBrandColor)،
 * ورفع شعار جديد يمر بفحص نوع فعلي وإعادة ترميز (App\Rules\BrandingImageFile). كل حفظ
 * يُسجَّل في audit_logs، ويُبطل كاش متغيرات CSS تلقائيًا (SiteBranding::booted()).
 */
class Branding extends PermissionPage
{
    use WithFileUploads;

    public const string AUDIT_UPDATED = 'branding.updated';

    protected static ?string $slug = 'content/branding';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    /** @var view-string */
    protected string $view = 'filament.pages.branding';

    public string $initiativeName = '';

    public string $primaryColorKey = '';

    public string $secondaryColorKey = '';

    public ?TemporaryUploadedFile $logoLight = null;

    public ?TemporaryUploadedFile $logoDark = null;

    public ?TemporaryUploadedFile $icon = null;

    public function mount(): void
    {
        $branding = SiteBranding::current();

        $this->initiativeName = $branding->initiative_name;
        $this->primaryColorKey = $branding->primary_color_key;
        $this->secondaryColorKey = $branding->secondary_color_key;
    }

    public static function getRequiredPermission(): PermissionKey
    {
        return PermissionKey::ContentManage;
    }

    public static function getNavigationLabel(): string
    {
        return __('branding.navigation');
    }

    public function getTitle(): string
    {
        return __('branding.navigation');
    }

    /**
     * @return array<string, array{label: string, light: string, dark: string}>
     */
    public function primaryOptions(): array
    {
        /** @var array<string, array{label: string, light: string, dark: string}> $palette */
        $palette = config('branding.palette.primary');

        return $palette;
    }

    /**
     * @return array<string, array{label: string, light: string, dark: string}>
     */
    public function secondaryOptions(): array
    {
        /** @var array<string, array{label: string, light: string, dark: string}> $palette */
        $palette = config('branding.palette.secondary');

        return $palette;
    }

    public function save(BrandingImageStorage $storage): void
    {
        $this->validate();

        $branding = SiteBranding::current();
        $changes = [];

        if ($branding->initiative_name !== $this->initiativeName) {
            $changes['initiative_name'] = ['from' => $branding->initiative_name, 'to' => $this->initiativeName];
        }

        if ($branding->primary_color_key !== $this->primaryColorKey) {
            $changes['primary_color_key'] = ['from' => $branding->primary_color_key, 'to' => $this->primaryColorKey];
        }

        if ($branding->secondary_color_key !== $this->secondaryColorKey) {
            $changes['secondary_color_key'] = ['from' => $branding->secondary_color_key, 'to' => $this->secondaryColorKey];
        }

        $actor = auth()->user();

        $branding->initiative_name = $this->initiativeName;
        $branding->primary_color_key = $this->primaryColorKey;
        $branding->secondary_color_key = $this->secondaryColorKey;
        $branding->updated_by = $actor instanceof User ? $actor->id : null;

        if ($this->logoLight instanceof TemporaryUploadedFile) {
            $storage->delete($branding->logo_light_path);
            $branding->logo_light_path = $storage->store($this->logoLight);
            $changes['logo_light_path'] = true;
        }

        if ($this->logoDark instanceof TemporaryUploadedFile) {
            $storage->delete($branding->logo_dark_path);
            $branding->logo_dark_path = $storage->store($this->logoDark);
            $changes['logo_dark_path'] = true;
        }

        if ($this->icon instanceof TemporaryUploadedFile) {
            $storage->delete($branding->icon_path);
            $branding->icon_path = $storage->store($this->icon);
            $changes['icon_path'] = true;
        }

        $branding->save();

        if ($changes !== []) {
            Audit::record(self::AUDIT_UPDATED, $branding, $changes);
        }

        $this->reset('logoLight', 'logoDark', 'icon');

        Notification::make()->title(__('branding.saved'))->success()->send();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'initiativeName' => ['required', 'string', 'min:2', 'max:255'],
            'primaryColorKey' => ['required', 'string', new AccessibleBrandColor(BrandColorPalette::ROLE_PRIMARY)],
            'secondaryColorKey' => ['required', 'string', new AccessibleBrandColor(BrandColorPalette::ROLE_SECONDARY)],
            'logoLight' => ['nullable', 'file', 'max:'.config('security.branding.max_kilobytes'), new BrandingImageFile],
            'logoDark' => ['nullable', 'file', 'max:'.config('security.branding.max_kilobytes'), new BrandingImageFile],
            'icon' => ['nullable', 'file', 'max:'.config('security.branding.max_kilobytes'), new BrandingImageFile],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'logoLight.max' => __('branding.validation.image_size'),
            'logoDark.max' => __('branding.validation.image_size'),
            'icon.max' => __('branding.validation.image_size'),
        ];
    }
}
