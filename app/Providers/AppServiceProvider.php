<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\PermissionKey;
use App\Policies\DeniesAbilitiesToEveryone;
use App\Policies\ReservesAbilitiesToPolicy;
use App\UserRole;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // يسمح باستخدام <x-layouts.app> في الصفحات العادية، بجانب استخدام
        // Livewire الخاص بالمكوّنات الكاملة عبر مساحة الاسم layouts:: (نفس الملف).
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        $this->registerPermissionGate();
    }

    /**
     * فحص الصلاحيات على الخادم في كل طلب (docs/SPEC.md §2, §12.5):
     * الحساب المعطَّل ممنوع من كل شيء، والمدير الفعّال يملك كل شيء ضمنيًا،
     * والمشرف يملك ما مُنح له مباشرة فقط وبشرط اعتمادياته.
     * ما تمنعه سياسة عن الجميع (DeniesAbilitiesToEveryone) ممنوع حتى على المدير،
     * وما تنفرد به سياسة (ReservesAbilitiesToPolicy) تقرّره هي وحدها ولو كان المستخدم مديرًا.
     */
    private function registerPermissionGate(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments): ?bool {
            if (! $user->is_active) {
                return false;
            }

            $policy = isset($arguments[0]) && (is_object($arguments[0]) || is_string($arguments[0]))
                ? Gate::getPolicyFor($arguments[0])
                : null;

            if ($policy instanceof DeniesAbilitiesToEveryone && in_array($ability, $policy->abilitiesDeniedToEveryone(), true)) {
                return false;
            }

            if ($policy instanceof ReservesAbilitiesToPolicy && in_array($ability, $policy->abilitiesReservedToPolicy(), true)) {
                return null;
            }

            if ($user->role === UserRole::Admin) {
                return true;
            }

            if ($user->role !== UserRole::Supervisor) {
                return null;
            }

            foreach (PermissionKey::tryFrom($ability)?->requires() ?? [] as $required) {
                if (! $user->hasGrantedPermission($required->value)) {
                    return false;
                }
            }

            return $user->hasGrantedPermission($ability) ? true : null;
        });
    }
}
