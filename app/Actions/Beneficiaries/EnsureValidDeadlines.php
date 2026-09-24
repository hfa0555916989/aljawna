<?php

declare(strict_types=1);

namespace App\Actions\Beneficiaries;

use App\Models\Beneficiary;
use App\Rules\NotInPast;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * مواعيد المستفيد متسلسلة: المستهدف ≤ المستحسن ≤ الزواج، ولا يُقبل موعد ماضٍ عند التسجيل
 * (قرار T05). يُفرض داخل الإجراءات حتى لو استُدعيت مباشرة دون النموذج.
 */
class EnsureValidDeadlines
{
    public const FIELDS = ['target_deadline', 'recommended_deadline', 'wedding_date'];

    /**
     * @throws ValidationException
     */
    public function handle(Beneficiary $beneficiary, bool $rejectPast): void
    {
        $deadlines = [];

        foreach (self::FIELDS as $field) {
            $deadlines[$field] = $beneficiary->getAttribute($field)?->toDateString();
        }

        $rules = [
            'target_deadline' => ['required', 'date'],
            'recommended_deadline' => ['required', 'date', 'after_or_equal:target_deadline'],
            'wedding_date' => ['required', 'date', 'after_or_equal:recommended_deadline'],
        ];

        if ($rejectPast) {
            foreach (self::FIELDS as $field) {
                $rules[$field][] = new NotInPast;
            }
        }

        Validator::make($deadlines, $rules, [
            'recommended_deadline.after_or_equal' => __('beneficiaries.validation.recommended_before_target'),
            'wedding_date.after_or_equal' => __('beneficiaries.validation.wedding_before_recommended'),
        ])->validate();
    }
}
