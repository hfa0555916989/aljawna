<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Support\TwoFactorEnrollment;
use App\Support\TwoFactorSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * إعداد التحقق بخطوتين داخل صفحة الرابط نفسها قبل أي وصول (docs/DECISIONS.md، T20):
 * قبول دعوة المشرف أو المدير، ورابط الإعداد من admin:reset-2fa.
 */
trait EnrollsTwoFactor
{
    public string $two_factor_code = '';

    /**
     * رموز الاسترداد الخام بعد التفعيل، تُعرض مرة واحدة ثم يغادر المستخدم الصفحة.
     *
     * @var list<string>
     */
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * سياق السر المعلَّق في الجلسة: الرابط نفسه، فلكل رابط سرّه.
     */
    abstract protected function enrollmentContext(): string;

    /**
     * @return array{qr: string, secret: string}
     */
    protected function enrollmentViewData(string $phone): array
    {
        $secret = TwoFactorEnrollment::pendingSecret($this->enrollmentContext());

        return [
            'qr' => TwoFactorEnrollment::qrCodeDataUri($secret, $phone),
            'secret' => TwoFactorEnrollment::groupedSecret($secret),
        ];
    }

    /**
     * يتحقق من الرمز المُدخل بالسر المعلَّق، ويعيد السر لحفظه في الحساب.
     *
     * @throws ValidationException
     */
    protected function confirmedTwoFactorSecret(): string
    {
        $secret = TwoFactorEnrollment::pendingSecret($this->enrollmentContext());

        if (! TwoFactorEnrollment::verify($secret, $this->two_factor_code)) {
            $this->reset('two_factor_code');

            throw ValidationException::withMessages([
                'two_factor_code' => __('auth.two_factor.invalid'),
            ]);
        }

        return $secret;
    }

    /**
     * دخول بجلسة اجتازت الخطوة الثانية للتو، ثم عرض رموز الاسترداد.
     *
     * @param  list<string>  $recoveryCodes
     */
    protected function completeEnrollment(User $user, array $recoveryCodes): void
    {
        TwoFactorEnrollment::forget($this->enrollmentContext());

        Auth::login($user);
        Session::regenerate();
        TwoFactorSession::markPassed();

        $this->reset('two_factor_code');
        $this->recoveryCodes = $recoveryCodes;
    }
}
