<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\PermissionKey;
use App\Support\HijriDate;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;

/**
 * حاسبة تحويل ثنائية الاتجاه بين الميلادي والهجري وفق أم القرى (docs/SPEC.md §8, FR-27).
 * تعرض رسالة واضحة عند اختيار يوم هجري غير موجود في شهره (مثل 30 في شهر من 29 يومًا).
 */
class Converter extends PermissionPage
{
    public const string DIRECTION_GREGORIAN_TO_HIJRI = 'gregorian_to_hijri';

    public const string DIRECTION_HIJRI_TO_GREGORIAN = 'hijri_to_gregorian';

    public const array DIRECTIONS = [self::DIRECTION_GREGORIAN_TO_HIJRI, self::DIRECTION_HIJRI_TO_GREGORIAN];

    protected static ?string $slug = 'converter';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    /** @var view-string */
    protected string $view = 'filament.pages.converter';

    public string $direction = self::DIRECTION_GREGORIAN_TO_HIJRI;

    public string $gregorianDate = '';

    public string $hijriYear = '';

    public string $hijriMonth = '';

    public string $hijriDay = '';

    public function mount(): void
    {
        $this->gregorianDate = CarbonImmutable::now(HijriDate::TIMEZONE)->toDateString();
    }

    public static function getRequiredPermission(): PermissionKey
    {
        return PermissionKey::ConverterUse;
    }

    public static function getNavigationLabel(): string
    {
        return __('converter.navigation');
    }

    public function getTitle(): string
    {
        return __('converter.navigation');
    }

    public function selectDirection(string $direction): void
    {
        if (! in_array($direction, self::DIRECTIONS, true)) {
            return;
        }

        $this->direction = $direction;
    }

    /**
     * هل أُكملت حقول الاتجاه الحالي؟ تُميّز بين "لم يُدخل شيء بعد" و"اليوم غير موجود".
     */
    #[Computed]
    public function hasCompleteInput(): bool
    {
        if ($this->direction === self::DIRECTION_GREGORIAN_TO_HIJRI) {
            return $this->gregorianDate !== '';
        }

        return $this->hijriYear !== '' && $this->hijriMonth !== '' && $this->hijriDay !== '';
    }

    /**
     * نتيجة التحويل بالتقويمين معًا، أو null إن كان اليوم المطلوب غير موجود (هجري) أو التاريخ غير صالح (ميلادي).
     *
     * @return array{hijri: string, gregorian: string, weekday: string}|null
     */
    #[Computed]
    public function result(): ?array
    {
        if (! $this->hasCompleteInput()) {
            return null;
        }

        if ($this->direction === self::DIRECTION_GREGORIAN_TO_HIJRI) {
            return HijriDate::dual($this->gregorianDate);
        }

        if (! ctype_digit($this->hijriYear) || ! ctype_digit($this->hijriMonth) || ! ctype_digit($this->hijriDay)) {
            return null;
        }

        $gregorian = HijriDate::toGregorian((int) $this->hijriYear, (int) $this->hijriMonth, (int) $this->hijriDay);

        return $gregorian === null ? null : HijriDate::dual($gregorian);
    }

    /**
     * أسماء الأشهر الهجرية من محرم إلى ذو الحجة، مرتبة بأرقامها (1 إلى 12).
     *
     * @return array<int, string>
     */
    #[Computed]
    public function hijriMonths(): array
    {
        /** @var array<int, string> */
        return __('converter.months');
    }
}
