<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.organization.manage') ?? false;
    }

    public function rules(): array
    {
        $model = app(\App\Modules\HR\Services\OrganizationCatalog::class)->model($this->route('kind'));
        $table = (new $model)->getTable();
        $rules = ['code' => ['required', 'string', 'max:50', \Illuminate\Validation\Rule::unique($table, 'code')->ignore($this->route('record'))],
            'name' => ['required', 'string', 'max:255'], 'name_en' => ['nullable', 'string', 'max:255'], 'active' => ['required', 'boolean']];
        if ($this->route('kind') === 'departments') {
            $rules['parent_id'] = ['nullable', 'integer', 'exists:hr_departments,id'];
        }
        if ($this->route('kind') === 'offices') {
            $rules['timezone'] = ['required', 'timezone', 'max:64'];
            $rules['city'] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['code.unique' => 'الرمز مستخدم بالفعل.', 'name.required' => 'الاسم مطلوب.', 'timezone.timezone' => 'المنطقة الزمنية غير صالحة.'];
    }
}
