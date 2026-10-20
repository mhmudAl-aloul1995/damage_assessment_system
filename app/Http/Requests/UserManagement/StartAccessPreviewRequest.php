<?php

namespace App\Http\Requests\UserManagement;

use App\Support\Access\AccessPreview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartAccessPreviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return app(AccessPreview::class)->canStart($this->user(), $this);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['user', 'role'])],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'role_id' => ['exclude_unless:mode,role', 'required', 'integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ];
    }

    public function messages(): array
    {
        return [
            'mode.required' => __('access_preview.invalid_mode'),
            'mode.in' => __('access_preview.invalid_mode'),
            'user_id.required' => __('access_preview.invalid_user'),
            'user_id.integer' => __('access_preview.invalid_user'),
            'user_id.exists' => __('access_preview.invalid_user'),
            'role_id.required' => __('access_preview.invalid_role'),
            'role_id.integer' => __('access_preview.invalid_role'),
            'role_id.exists' => __('access_preview.invalid_role'),
        ];
    }
}
