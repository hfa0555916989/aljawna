<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Beneficiary;
use App\Models\Transfer;
use App\PermissionKey;
use App\Services\ReceiptStorage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

/**
 * إيصالات الحوالات مجمَّعة تحت كل مبادرة (docs/SPEC.md §12.6، قرار T07).
 *
 * المعيار صلاحية transfers.view العامة، لا منشئ المبادرة: من يملكها يرى كل المبادرات وإيصالاتها.
 * روابط الإيصالات موقّعة مؤقتة كغيرها، ويُعاد فحص TransferPolicy عند فتحها.
 */
class BeneficiaryReceipts extends PermissionPage
{
    use WithPagination;

    public const int PER_PAGE = 10;

    public const int TRANSFERS_PER_PAGE = 15;

    protected static ?string $slug = 'beneficiary-receipts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    /**
     * @var view-string
     */
    protected string $view = 'filament.pages.beneficiary-receipts';

    public static function getRequiredPermission(): PermissionKey
    {
        return PermissionKey::TransfersView;
    }

    public static function getNavigationLabel(): string
    {
        return __('transfers.admin.navigation');
    }

    public function getTitle(): string
    {
        return __('transfers.admin.title');
    }

    public function getSubheading(): string
    {
        return __('transfers.admin.lead');
    }

    /**
     * المبادرات الأحدث نشاطًا أولًا، ثم التي لم تصلها حوالات بترتيب تسجيلها.
     *
     * @return LengthAwarePaginator<int, Beneficiary>
     */
    #[Computed]
    public function beneficiaries(): LengthAwarePaginator
    {
        return Beneficiary::query()
            ->select(['id', 'display_name', 'status', 'approved_at', 'target_amount'])
            ->withCount('transfers')
            ->withSum('transfers', 'amount')
            ->withMax('transfers', 'created_at')
            ->orderByRaw('transfers_max_created_at desc nulls last')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);
    }

    /**
     * حوالات المبادرة الواحدة بترقيم مستقل لكل مبادرة، حتى لا تثقل الصفحة إن تراكمت بالمئات.
     *
     * @return LengthAwarePaginator<int, Transfer>
     */
    public function transfersOf(Beneficiary $beneficiary): LengthAwarePaginator
    {
        return $beneficiary->transfers()
            ->select(['id', 'user_id', 'beneficiary_id', 'amount', 'transferred_on', 'bank_reference', 'is_repeated', 'receipt_path'])
            ->with('user:id,full_name')
            ->orderByDesc('transferred_on')
            ->orderByDesc('id')
            ->paginate(self::TRANSFERS_PER_PAGE, pageName: self::transfersPageName($beneficiary));
    }

    public static function transfersPageName(Beneficiary $beneficiary): string
    {
        return 'transfers_'.$beneficiary->id;
    }

    public function receiptUrl(Transfer $transfer): string
    {
        return app(ReceiptStorage::class)->temporaryUrl($transfer);
    }
}
