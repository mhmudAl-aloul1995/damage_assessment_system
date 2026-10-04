<?php

namespace App\Http\Requests\UserManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Database Officer')
            || ($this->user()?->can($this->isMethod('POST') ? 'roles.create' : 'roles.update') ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->route('role'))],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
            'revision' => [$this->isMethod('POST') ? 'nullable' : 'required', 'string', 'size:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('access.name_required'),
            'name.unique' => __('access.name_unique'),
            'permissions.present' => __('access.permissions_required'),
            'permissions.array' => __('access.permissions_required'),
            'permissions.*.exists' => __('access.invalid_permission'),
            'revision.required' => __('access.conflict'),
            'revision.size' => __('access.conflict'),
        ];
    }
}
