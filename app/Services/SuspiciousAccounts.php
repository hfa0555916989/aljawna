<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * قواعد الحسابات المشبوهة لتبويب الأمان (docs/SPEC.md §12.7). العتبات في config/security.php.
 * تشمل حسابات المبادرين وحدها، لأن تعطيلها هو ما يتيحه users.suspend (FR-22).
 */
final class SuspiciousAccounts
{
    public const string RULE_FAILED_LOGINS = 'failed_logins';

    public const string RULE_REGISTRATIONS_PER_IP = 'registrations_per_ip';

    public const string RULE_RECOVERY_REQUESTS = 'recovery_requests';

    /**
     * الحسابات التي بلغت عتبة قاعدة واحدة على الأقل، مع كل قاعدة بلغتها وعددها.
     *
     * @return list<array{user: User, flags: list<array{rule: string, count: int}>}>
     */
    public function detect(): array
    {
        /** @var array<int, list<array{rule: string, count: int}>> $flags */
        $flags = [];

        foreach ([
            self::RULE_FAILED_LOGINS => $this->failedLogins(),
            self::RULE_REGISTRATIONS_PER_IP => $this->registrationsPerIp(),
            self::RULE_RECOVERY_REQUESTS => $this->recoveryRequests(),
        ] as $rule => $counts) {
            foreach ($counts as $userId => $count) {
                $flags[$userId][] = ['rule' => $rule, 'count' => $count];
            }
        }

        if ($flags === []) {
            return [];
        }

        return array_values(User::query()
            ->whereKey(array_keys($flags))
            ->orderByDesc('id')
            ->get(['id', 'full_name', 'phone', 'is_active', 'role'])
            ->map(fn (User $user): array => ['user' => $user, 'flags' => $flags[$user->id]])
            ->all());
    }

    /**
     * @return array<int, int> معرّف الحساب => عدد المحاولات الفاشلة برقمه.
     */
    private function failedLogins(): array
    {
        [$threshold, $since] = $this->rule(self::RULE_FAILED_LOGINS);

        /** @var array<string, int|string> $countsByPhone */
        $countsByPhone = LoginAttempt::query()
            ->toBase()
            ->selectRaw('phone, count(*) as total')
            ->where('succeeded', false)
            ->where('created_at', '>=', $since)
            ->groupBy('phone')
            ->havingRaw('count(*) >= ?', [$threshold])
            ->pluck('total', 'phone')
            ->all();

        if ($countsByPhone === []) {
            return [];
        }

        $counts = [];

        foreach ($this->initiators()->whereIn('phone', array_keys($countsByPhone))->pluck('phone', 'id') as $id => $phone) {
            $counts[(int) $id] = (int) $countsByPhone[$phone];
        }

        return $counts;
    }

    /**
     * @return array<int, int> معرّف الحساب => عدد الحسابات المسجّلة من IP تسجيله خلال الفترة.
     */
    private function registrationsPerIp(): array
    {
        [$threshold, $since] = $this->rule(self::RULE_REGISTRATIONS_PER_IP);

        /** @var array<string, int|string> $countsByIp */
        $countsByIp = $this->initiators()
            ->toBase()
            ->selectRaw('registered_ip, count(*) as total')
            ->whereNotNull('registered_ip')
            ->where('created_at', '>=', $since)
            ->groupBy('registered_ip')
            ->havingRaw('count(*) >= ?', [$threshold])
            ->pluck('total', 'registered_ip')
            ->all();

        if ($countsByIp === []) {
            return [];
        }

        $counts = [];

        foreach (
            $this->initiators()
                ->whereIn('registered_ip', array_keys($countsByIp))
                ->where('created_at', '>=', $since)
                ->pluck('registered_ip', 'id') as $id => $ip
        ) {
            $counts[(int) $id] = (int) $countsByIp[$ip];
        }

        return $counts;
    }

    /**
     * @return array<int, int> معرّف الحساب => عدد طلبات الاستعادة لحسابه.
     */
    private function recoveryRequests(): array
    {
        [$threshold, $since] = $this->rule(self::RULE_RECOVERY_REQUESTS);

        /** @var array<int|string, int|string> $countsByUser */
        $countsByUser = PasswordResetRequest::query()
            ->toBase()
            ->selectRaw('user_id, count(*) as total')
            ->where('created_at', '>=', $since)
            ->groupBy('user_id')
            ->havingRaw('count(*) >= ?', [$threshold])
            ->pluck('total', 'user_id')
            ->all();

        if ($countsByUser === []) {
            return [];
        }

        $counts = [];

        foreach ($this->initiators()->whereKey(array_keys($countsByUser))->pluck('id') as $id) {
            $counts[(int) $id] = (int) $countsByUser[$id];
        }

        return $counts;
    }

    /**
     * @return Builder<User>
     */
    private function initiators(): Builder
    {
        return User::query()->where('role', UserRole::User);
    }

    /**
     * @return array{0: int, 1: Carbon}
     */
    private function rule(string $rule): array
    {
        return [
            max(1, (int) config("security.suspicious.{$rule}.threshold")),
            now()->subHours(max(1, (int) config("security.suspicious.{$rule}.hours"))),
        ];
    }
}
