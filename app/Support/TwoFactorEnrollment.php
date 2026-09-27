<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SiteBranding;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use PragmaRX\Google2FAQRCode\Google2FA;
use SensitiveParameter;

/**
 * إعداد التحقق بخطوتين قبل إنشاء الحساب أو الوصول إليه (docs/DECISIONS.md، T20):
 * في صفحتي قبول دعوة المشرف والمدير، وفي رابط الإعداد من admin:reset-2fa.
 *
 * السر المعلَّق يُحفظ في الجلسة على الخادم (لا في خصائص Livewire العامة)،
 * مربوطًا بالرابط نفسه، فيبقى رمز QR ثابتًا عند إعادة تحميل الصفحة، ولا يُحفظ
 * في الحساب إلا بعد رمز صحيح منه. يعتمد مزوّد Filament نفسه (AppAuthentication):
 * السر مشفَّر ورموز الاسترداد مجزّأة ومنع إعادة استخدام الرمز.
 */
final class TwoFactorEnrollment
{
    private const string SESSION_PREFIX = 'two_factor_enrollment.';

    private function __construct()
    {
        //
    }

    /**
     * السر المعلَّق لهذا الرابط، ويُنشأ عند أول طلب.
     */
    public static function pendingSecret(string $context): string
    {
        $key = self::sessionKey($context);
        $secret = session()->get($key);

        if (! is_string($secret) || $secret === '') {
            $secret = self::provider()->generateSecret();
            session()->put($key, $secret);
        }

        return $secret;
    }

    public static function forget(string $context): void
    {
        session()->forget(self::sessionKey($context));
    }

    /**
     * رمز QR لتطبيق المصادقة بصيغة data URI، باسم المبادرة ورقم الدخول (لا بريد في النظام).
     */
    public static function qrCodeDataUri(#[SensitiveParameter] string $secret, string $phone): string
    {
        $qrCode = app(Google2FA::class)->getQRCodeInline(SiteBranding::current()->initiative_name, $phone, $secret);

        return str_starts_with($qrCode, 'data:') ? $qrCode : 'data:image/svg+xml;base64,'.base64_encode($qrCode);
    }

    /**
     * السر مجمّعًا أربعة أربعة لإدخاله يدويًا في تطبيق المصادقة.
     */
    public static function groupedSecret(#[SensitiveParameter] string $secret): string
    {
        return implode(' ', str_split($secret, 4));
    }

    public static function verify(#[SensitiveParameter] string $secret, #[SensitiveParameter] string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        return preg_match('/^\d{6}$/', $code) === 1
            && self::provider()->verifyCode($code, $secret, shouldPreventCodeReuse: true);
    }

    /**
     * يحفظ السر في الحساب مع رموز استرداد جديدة، ويعيد الرموز الخام لعرضها مرة واحدة.
     *
     * @return list<string>
     */
    public static function enable(User $user, #[SensitiveParameter] string $secret): array
    {
        $provider = self::provider();

        $provider->saveSecret($user, $secret);

        return self::regenerateRecoveryCodes($user);
    }

    /**
     * رموز استرداد جديدة تُبطل كل الرموز السابقة، وتُعاد خامًا لعرضها مرة واحدة.
     *
     * @return list<string>
     */
    public static function regenerateRecoveryCodes(User $user): array
    {
        $provider = self::provider();
        $codes = array_values($provider->generateRecoveryCodes());

        $provider->saveRecoveryCodes($user, $codes);

        return $codes;
    }

    public static function provider(): AppAuthentication
    {
        $provider = Filament::getPanel('admin')->getMultiFactorAuthenticationProviders()['app'] ?? null;

        return $provider instanceof AppAuthentication ? $provider : AppAuthentication::make()->recoverable();
    }

    private static function sessionKey(string $context): string
    {
        return self::SESSION_PREFIX.hash('sha256', $context);
    }
}
