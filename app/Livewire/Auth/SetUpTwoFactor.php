<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\LoginUser;
use App\Livewire\Concerns\EnrollsTwoFactor;
use App\Models\TwoFactorSetupLink;
use App\Models\User;
use App\Services\Audit;
use App\Support\TwoFactorEnrollment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * إعداد التحقق بخطوتين من رابط php artisan admin:reset-2fa (docs/DECISIONS.md، T20).
 * لا رابط لهذه الصفحة في أي قائمة. يثبت صاحب الحساب هويته بالرابط وكلمة المرور
 * معًا (بنفس قفل الدخول وسجله)، ثم يُعدّ تطبيق المصادقة ويدخل بجلسة مجتازة.
 */
#[Layout('layouts.app')]
#[Title('إعداد التحقق بخطوتين')]
class SetUpTwoFactor extends Component
{
    use EnrollsTwoFactor;

    public const string AUDIT_ACTION = 'auth.two_factor_enabled';

    #[Locked]
    public string $token = '';

    public string $password = '';

    public bool $invalid = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->invalid = $this->usableLink() === null;
    }

    public function setUp(LoginUser $loginUser): void
    {
        $link = $this->usableLink();

        if ($link === null) {
            $this->invalid = true;

            throw ValidationException::withMessages(['form' => __('auth.two_factor.setup_link.invalid')]);
        }

        $this->validate([
            'password' => ['required', 'string', 'max:255'],
            'two_factor_code' => ['required', 'string', 'max:16'],
        ]);

        /** @var User $owner */
        $owner = $link->user;
        $ip = (string) request()->ip();

        try {
            $loginUser->verifyCredentials($owner->phone, $this->password, $ip, forTwoFactorSetup: true);
        } catch (ValidationException $exception) {
            $this->reset('password');

            throw ValidationException::withMessages(['password' => $exception->errors()['phone'] ?? __('auth.failed')]);
        }

        $this->reset('password');

        $secret = $this->confirmedTwoFactorSecret();

        $recoveryCodes = DB::transaction(function () use ($link, $secret): array {
            $locked = TwoFactorSetupLink::query()->whereKey($link->id)->lockForUpdate()->first();
            $user = User::query()->whereKey($link->user_id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->isUsable() || ! $user instanceof User || $user->hasTwoFactorEnabled()) {
                throw ValidationException::withMessages(['form' => __('auth.two_factor.setup_link.invalid')]);
            }

            $codes = TwoFactorEnrollment::enable($user, $secret);
            $locked->forceFill(['used_at' => now()])->save();

            Audit::record(self::AUDIT_ACTION, $user, ['issued_via' => 'setup_link', 'setup_link_id' => $locked->id], $user);

            return $codes;
        });

        $owner->refresh();
        $loginUser->recordSuccess($owner, $ip);

        $this->completeEnrollment($owner, $recoveryCodes);
    }

    public function render(): View
    {
        $link = $this->invalid || $this->recoveryCodes !== [] ? null : $this->usableLink();

        return view('livewire.auth.set-up-two-factor', [
            'enrollment' => $link?->user === null ? null : $this->enrollmentViewData($link->user->phone),
        ]);
    }

    protected function enrollmentContext(): string
    {
        return 'two-factor-setup:'.$this->token;
    }

    /**
     * رابط صالح لم يُستخدم، لحساب إداري فعّال لم يُعدّ التحقق بعد.
     */
    private function usableLink(): ?TwoFactorSetupLink
    {
        $link = TwoFactorSetupLink::findByToken($this->token);
        $owner = $link?->user;

        if ($link === null || ! $link->isUsable() || ! $owner instanceof User) {
            return null;
        }

        return $owner->is_active && $owner->hasPanelRole() && ! $owner->hasTwoFactorEnabled() ? $link : null;
    }
}
