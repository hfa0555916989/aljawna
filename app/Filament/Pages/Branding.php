<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Exceptions\StorageOperationFailed;
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
use Illuminate\Support\Facades\Log;
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

        // الصور الجديدة تُحفظ أولًا: إن فشل أيٌّ منها لا يتغير شيء، وتُحذف القديمة بعد الحفظ فقط.
        $uploads = array_filter([
            'logo_light_path' => $this->logoLight,
            'logo_dark_path' => $this->logoDark,
            'icon_path' => $this->icon,
        ], fn (mixed $upload): bool => $upload instanceof TemporaryUploadedFile);

        $stored = [];
        $replaced = [];

        try {
            foreach ($uploads as $column => $upload) {
                $stored[$column] = $storage->store($upload);
            }
        } catch (StorageOperationFailed) {
            foreach ($stored as $path) {
                rescue(fn () => $storage->delete($path), report: false);
            }

            Notification::make()->title(__('branding.store_failed'))->danger()->persistent()->send();

            return;
        }

        foreach ($stored as $column => $path) {
            $replaced[] = $branding->{$column};
            $branding->{$column} = $path;
            $changes[$column] = true;
        }

        $branding->save();

        if ($changes !== []) {
            Audit::record(self::AUDIT_UPDATED, $branding, $changes);
        }

        $this->reset('logoLight', 'logoDark', 'icon');

        Notification::make()->title(__('branding.saved'))->success()->send();

        $this->deleteReplacedImages($storage, $replaced);
    }

    /**
     * حذف الصور القديمة بعد حفظ البديلة. فشل الحذف لا يلغي الحفظ، ويُبلَّغ في اللوحة.
     *
     * @param  list<string|null>  $paths
     */
    private function deleteReplacedImages(BrandingImageStorage $storage, array $paths): void
    {
        $failed = false;

        foreach ($paths as $path) {
            try {
                $storage->delete($path);
            } catch (StorageOperationFailed) {
                $failed = true;
                Log::warning('Replaced branding image could not be deleted.');
            }
        }

        if ($failed) {
            Notification::make()->title(__('branding.delete_failed'))->warning()->send();
        }
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
