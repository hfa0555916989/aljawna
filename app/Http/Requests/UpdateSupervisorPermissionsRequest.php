<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\PermissionKey;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSupervisorPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(PermissionKey::SupervisorsManage->value);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', 'max:64'],
        ];
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        /** @var list<string> $permissions */
        $permissions = array_values($this->validated('permissions'));

        return $permissions;
    }
}
