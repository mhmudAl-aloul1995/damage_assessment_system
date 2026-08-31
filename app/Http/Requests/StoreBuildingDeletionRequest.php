<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StoreBuildingDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'building_globalid' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                $existsInBuildings = Schema::hasTable('buildings')
                    && DB::table('buildings')->where('globalid', $value)->exists();
                $existsInAuditedBuildings = Schema::hasTable('audited_buildings')
                    && DB::table('audited_buildings')->where('globalid', $value)->exists();

                if (! $existsInBuildings && ! $existsInAuditedBuildings) {
                    $fail(__('validation.exists', ['attribute' => $attribute]));
                }
            }],
            'reason' => ['required', 'string', 'min:10'],
            'notes' => ['nullable', 'string'],
            'signature' => ['required', 'string'],
            'confirmation' => ['accepted'],
        ];
    }
}
