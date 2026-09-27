<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Audit;
use App\Support\TwoFactorEnrollment;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * تجديد رموز الاسترداد لكل صاحب تحقق بخطوتين (docs/DECISIONS.md، T20)، بعد رمز
 * TOTP حالي من تطبيقه (بمنع إعادة استخدام الرمز)، فلا تكفي جلسة مفتوحة وحدها.
 * الرموز الجديدة تُبطل السابقة وتُعرض مرة واحدة، والتجديد يُسجَّل في سجل التدقيق.
 * الرموز الخاطئة محدودة لكل حساب (security.two_factor.recovery_regeneration_*).
 */
class RecoveryCodes extends Page
{
    public const string AUDIT_ACTION = 'auth.recovery_codes_regenerated';

    protected static ?string $slug = 'two-factor/recovery-codes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static bool $shouldRegisterNavigation = false;

    /** @var view-string */
    protected string $view = 'filament.pages.recovery-codes';

    public string $code = '';

    /**
     * @var list<string>
     */
    #[Locked]
    public array $recoveryCodes = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_active && $user->hasPanelRole() && $user->hasTwoFactorEnabled();
    }

    public static function getNavigationLabel(): string
    {
        return __('auth.two_factor.recovery_codes.regenerate_title');
    }

    public function getTitle(): string
    {
        return __('auth.two_factor.recovery_codes.regenerate_title');
    }

    public function remainingCount(): int
    {
        return count($this->user()->getAppAuthenticationRecoveryCodes() ?? []);
    }

    public function regenerate(): void
    {
        $this->validate(['code' => ['required', 'string', 'max:16']]);

        $user = $this->user();
        $key = 'two-factor:recovery-regeneration:'.$user->getKey();
        $maxAttempts = (int) config('security.two_factor.recovery_regeneration_max_attempts');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->reset('code');

            throw ValidationException::withMessages([
                'code' => __('auth.two_factor.recovery_codes.throttled', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
            ]);
        }

        if (! TwoFactorEnrollment::verify(TwoFactorEnrollment::provider()->getSecret($user), $this->code)) {
            RateLimiter::hit($key, (int) config('security.two_factor.recovery_regeneration_decay_minutes') * 60);
            $this->reset('code');

            throw ValidationException::withMessages(['code' => __('auth.two_factor.invalid')]);
        }

        RateLimiter::clear($key);

        $previous = $this->remainingCount();
        $this->recoveryCodes = TwoFactorEnrollment::regenerateRecoveryCodes($user);
        $this->reset('code');

        Audit::record(self::AUDIT_ACTION, $user, [
            'previous_remaining' => $previous,
            'count' => count($this->recoveryCodes),
        ], $user);
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
