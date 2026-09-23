<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Supervisors\UpdateSupervisorPermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSupervisorPermissionsRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * PUT /admin/supervisors/{supervisor}/permissions (docs/SPEC.md §10، بصلاحية supervisors.manage).
 */
class UpdateSupervisorPermissionsController extends Controller
{
    public function __invoke(
        UpdateSupervisorPermissionsRequest $request,
        User $supervisor,
        UpdateSupervisorPermissions $updateSupervisorPermissions,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $updateSupervisorPermissions->handle($actor, $supervisor, $request->permissions());

        return back()->with('status', __('permissions.updated'));
    }
}
