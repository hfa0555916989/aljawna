<?php

declare(strict_types=1);

namespace App;

/**
 * حالة المطابقة الاختيارية للحوالة (docs/SPEC.md §9 transfers, FR-43).
 * لا تؤثر في احتساب الحوالة؛ فلا توجد حالة "قيد المراجعة" ولا "رفض" (FR-14).
 */
enum TransferReviewState: string
{
    case NotReviewed = 'not_reviewed';
    case Matched = 'matched';
    case Commented = 'commented';
    case FinalReviewed = 'final_reviewed';
}
