<?php

declare(strict_types=1);

namespace App\Models;

use App\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasPermissions;

#[Fillable(['full_name', 'phone', 'password', 'role', 'is_active', 'show_contact', 'registered_ip', 'last_login_ip', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPermissions, Notifiable;

    /**
     * هل مُنح المستخدم هذه الصلاحية مباشرة؟ لا يشمل منح المدير الضمني؛
     * للفحص الكامل استخدم can().
     */
    public function hasGrantedPermission(string $permission): bool
    {
        try {
            return $this->hasDirectPermission($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public function isActiveAdmin(): bool
    {
        return $this->role === UserRole::Admin && $this->is_active;
    }

    /**
     * يبقى مدير فعّال واحد على الأقل في النظام (docs/SPEC.md §2).
     */
    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            $wasActiveAdmin = $user->getOriginal('role') === UserRole::Admin && $user->getOriginal('is_active') === true;

            if ($wasActiveAdmin && ! $user->isActiveAdmin()) {
                $user->ensureAnotherActiveAdminExists();
            }
        });

        static::deleting(function (User $user): void {
            if ($user->isActiveAdmin()) {
                $user->ensureAnotherActiveAdminExists();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'show_contact' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureAnotherActiveAdminExists(): void
    {
        $otherActiveAdmins = static::query()
            ->whereKeyNot($this->getKey())
            ->where('role', UserRole::Admin)
            ->where('is_active', true)
            ->exists();

        if (! $otherActiveAdmins) {
            throw new AuthorizationException(__('permissions.errors.last_admin'));
        }
    }
}
