<?php

declare(strict_types=1);

namespace App\Actions\Supervisors;

use App\Models\User;
use App\PermissionKey;
use App\Services\Audit;
use App\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * نقطة التعديل الوحيدة لصلاحيات مشرف، بقواعد docs/SPEC.md §2:
 * لا يعدّل المشرف صلاحيات نفسه، ولا يمنح ما لا يملكه، ولا يمنح صلاحية حساسة
 * (المدير وحده يمنحها)، ولا تُمس صلاحيات المدير، وتُفرض اعتماديات الصلاحيات،
 * ويُسجَّل كل تغيير في سجل التدقيق.
 */
class UpdateSupervisorPermissions
{
    public const AUDIT_ACTION = 'permissions.updated';

    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * هل يستطيع هذا الفاعل منح هذه الصلاحيات دفعة واحدة؟ تُستخدم عند الدعوة
     * قبل وجود المشرف، وبنفس قواعد المنح في التعديل.
     *
     * @param  list<string>  $permissions
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function assertCanGrant(User $actor, array $permissions): void
    {
        if (! $actor->can(PermissionKey::SupervisorsManage->value)) {
            throw new AuthorizationException(__('permissions.errors.unauthorized'));
        }

        $requested = array_values(array_unique($permissions));

        $this->ensurePermissionsExist($requested);
        $this->ensureDependenciesAreMet($requested);
        $this->ensureActorOwns($actor, $requested);
    }

    /**
     * يضبط صلاحيات المشرف المباشرة لتساوي القائمة المعطاة تمامًا.
     *
     * @param  list<string>  $permissions
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, User $supervisor, array $permissions): void
    {
        $this->authorize($actor, $supervisor);

        $requested = array_values(array_unique($permissions));

        $this->ensurePermissionsExist($requested);
        $this->ensureDependenciesAreMet($requested);

        $current = $supervisor->permissions()->pluck('name')->all();
        $granted = array_values(array_diff($requested, $current));
        $revoked = array_values(array_diff($current, $requested));

        $this->ensureActorOwns($actor, $granted);

        if ($granted === [] && $revoked === []) {
            return;
        }

        DB::transaction(function () use ($actor, $supervisor, $requested, $granted, $revoked): void {
            $supervisor->syncPermissions($requested);

            Audit::record(self::AUDIT_ACTION, $supervisor, [
                'granted' => $granted,
                'revoked' => $revoked,
                'permissions' => $requested,
            ], $actor);
        });

        $this->registrar->forgetCachedPermissions();
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(User $actor, User $supervisor): void
    {
        if (! $actor->can(PermissionKey::SupervisorsManage->value)) {
            throw new AuthorizationException(__('permissions.errors.unauthorized'));
        }

        if ($supervisor->role === UserRole::Admin) {
            throw new AuthorizationException(__('permissions.errors.admin'));
        }

        if ($actor->is($supervisor)) {
            throw new AuthorizationException(__('permissions.errors.self'));
        }

        if ($supervisor->role !== UserRole::Supervisor) {
            throw new AuthorizationException(__('permissions.errors.not_supervisor'));
        }
    }

    /**
     * @param  list<string>  $permissions
     *
     * @throws ValidationException
     */
    private function ensurePermissionsExist(array $permissions): void
    {
        $known = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $permissions)
            ->pluck('name')
            ->all();

        $unknown = array_diff($permissions, $known);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'permissions' => __('permissions.errors.unknown', ['permissions' => implode('، ', $unknown)]),
            ]);
        }
    }

    /**
     * @param  list<string>  $permissions
     *
     * @throws ValidationException
     */
    private function ensureDependenciesAreMet(array $permissions): void
    {
        foreach ($permissions as $permission) {
            foreach (PermissionKey::tryFrom($permission)?->requires() ?? [] as $required) {
                if (! in_array($required->value, $permissions, true)) {
                    throw ValidationException::withMessages([
                        'permissions' => __('permissions.errors.requires', [
                            'permission' => $permission,
                            'required' => $required->value,
                        ]),
                    ]);
                }
            }
        }
    }

    /**
     * @param  list<string>  $granted
     *
     * @throws AuthorizationException
     */
    private function ensureActorOwns(User $actor, array $granted): void
    {
        $sensitive = array_values(array_filter(
            $granted,
            fn (string $permission): bool => PermissionKey::tryFrom($permission)?->isSensitive() ?? false,
        ));

        if ($sensitive !== [] && ! $actor->isActiveAdmin()) {
            throw new AuthorizationException(__('permissions.errors.sensitive_admin_only', [
                'permissions' => implode('، ', $sensitive),
            ]));
        }

        $notOwned = array_values(array_filter($granted, fn (string $permission): bool => ! $actor->can($permission)));

        if ($notOwned !== []) {
            throw new AuthorizationException(__('permissions.errors.grant_unowned', [
                'permissions' => implode('، ', $notOwned),
            ]));
        }
    }
}
