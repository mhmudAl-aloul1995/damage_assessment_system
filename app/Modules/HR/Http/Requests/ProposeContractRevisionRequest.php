<?php

namespace App\Modules\HR\Http\Requests;

use Illuminate\Validation\Rule;

class ProposeContractRevisionRequest extends StoreContractRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('contract')) ?? false;
    }

    public function rules(): array
    {
        return [...$this->revisionRules(), 'kind' => ['required', Rule::in(['initial', 'amendment', 'renewal', 'termination'])], 'effective_on' => ['nullable', 'date_format:Y-m-d']];
    }
}
