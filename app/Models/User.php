<?php

declare(strict_types=1);

namespace App\Models;

use App\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasPermissions;

#[Fillable(['full_name', 'phone', 'password', 'role', 'is_active', 'show_contact', 'registered_ip', 'last_login_ip', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPermissions, Notifiable;

    /**
     * لوحة الإدارة للمشرف والمدير الفعّالين فقط، وتُفحص في كل طلب (docs/SPEC.md §2).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->is_active
            && $this->hasPanelRole();
    }

    /**
     * هل دور المستخدم من أدوار لوحة الإدارة (مشرف أو مدير)؟ لا يفحص التفعيل.
     */
    public function hasPanelRole(): bool
    {
        return in_array($this->role, [UserRole::Supervisor, UserRole::Admin], true);
    }

    /**
     * وجهة المستخدم بعد الدخول أو عند زيارة صفحة الدخول بجلسة قائمة:
     * المشرف والمدير إلى /admin، والمبادر إلى /dashboard.
     */
    public function homeUrl(): string
    {
        return $this->hasPanelRole()
            ? Dashboard::getUrl(panel: 'admin')
            : route('dashboard');
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    /**
     * الاسم الأول فقط، للقوائم العامة التي لا تكشف اسم المبادر كاملًا (docs/SPEC.md §12.6).
     */
    public function firstName(): string
    {
        return Str::of($this->full_name)->squish()->before(' ')->toString();
    }

    /**
     * مشرف لم يُمنح أي صلاحية مباشرة بعد.
     */
    public function isSupervisorWithoutPermissions(): bool
    {
        return $this->role === UserRole::Supervisor && ! $this->permissions()->exists();
    }

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

    /**
     * الحوالات التي بادر بها (FR-16).
     *
     * @return HasMany<Transfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
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
