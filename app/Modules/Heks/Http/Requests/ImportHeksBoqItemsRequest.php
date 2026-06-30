<?php

namespace App\Modules\Heks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportHeksBoqItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->hasRole('Database Officer') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
        ];
    }
}
