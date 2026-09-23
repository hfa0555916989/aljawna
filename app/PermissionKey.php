<?php

declare(strict_types=1);

namespace App;

/**
 * مفاتيح الصلاحيات القابلة للمنح للمشرفين (docs/SPEC.md §2).
 */
enum PermissionKey: string
{
    /**
     * الصلاحيات الحساسة جدًا: يمنحها المدير وحده (docs/SPEC.md §2, §12.5).
     */
    public const SENSITIVE_PERMISSIONS = [
        self::RecoveryOtherNumber,
        self::RecoveryChangePhone,
        self::BeneficiariesManage,
        self::SettingsManage,
        self::ContentManage,
        self::SupervisorsManage,
    ];

    case StatsView = 'stats.view';
    case TransfersView = 'transfers.view';
    case TransfersReview = 'transfers.review';
    case TransfersAssign = 'transfers.assign';
    case UsersView = 'users.view';
    case UsersSuspend = 'users.suspend';
    case RecoveryHandle = 'recovery.handle';
    case RecoveryOtherNumber = 'recovery.other_number';
    case RecoveryChangePhone = 'recovery.change_phone';
    case SecurityView = 'security.view';
    case StatsRecovery = 'stats.recovery';
    case SettingsManage = 'settings.manage';
    case BeneficiariesManage = 'beneficiaries.manage';
    case ContentManage = 'content.manage';
    case MessagesView = 'messages.view';
    case ConverterUse = 'converter.use';
    case SupervisorsManage = 'supervisors.manage';

    public function isSensitive(): bool
    {
        return in_array($this, self::SENSITIVE_PERMISSIONS, true);
    }

    /**
     * الصلاحيات التي لا تعمل هذه الصلاحية بدونها.
     *
     * @return list<self>
     */
    public function requires(): array
    {
        return match ($this) {
            self::RecoveryOtherNumber, self::RecoveryChangePhone => [self::RecoveryHandle],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $key): string => $key->value, self::cases());
    }
}
