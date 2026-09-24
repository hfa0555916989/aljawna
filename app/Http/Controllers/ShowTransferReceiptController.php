<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Transfer;
use App\Services\ReceiptStorage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * عرض إيصال حوالة (docs/SPEC.md §12.6): رابط موقّع مؤقت، ثم فحص Policy عند كل طلب.
 * التوقيع وحده لا يكفي؛ من حصل على رابط غيره يُرفض بـ 403.
 */
class ShowTransferReceiptController extends Controller
{
    public function __invoke(Transfer $transfer, ReceiptStorage $receipts): StreamedResponse
    {
        Gate::authorize('viewReceipt', $transfer);

        return $receipts->response($transfer);
    }
}
