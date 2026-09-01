<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Validator;

class StoreBuildingDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'building_globalid' => ['nullable', 'string', $this->existingBuildingGlobalIdRule()],
            'building_globalids' => ['nullable', 'array'],
            'building_globalids.*' => ['string', $this->existingBuildingGlobalIdRule()],
            'building_objectids_text' => ['nullable', 'string'],
            'reason' => ['required', 'string', 'min:10'],
            'notes' => ['nullable', 'string'],
            'confirmation' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasSingleBuilding = filled($this->input('building_globalid'));
            $hasMultipleBuildings = collect((array) $this->input('building_globalids', []))
                ->filter(fn (mixed $value): bool => filled($value))
                ->isNotEmpty();
            $hasPastedObjectIds = collect(preg_split('/[\s,;]+/', (string) $this->input('building_objectids_text'), -1, PREG_SPLIT_NO_EMPTY))
                ->isNotEmpty();

            if (! $hasSingleBuilding && ! $hasMultipleBuildings && ! $hasPastedObjectIds) {
                $validator->errors()->add('building_globalids', __('ui.building_deletions.messages.select_at_least_one_building'));
            }
        });
    }

    private function existingBuildingGlobalIdRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! filled($value)) {
                return;
            }

            $existsInBuildings = Schema::hasTable('buildings')
                && DB::table('buildings')->where('globalid', $value)->exists();
            $existsInAuditedBuildings = Schema::hasTable('audited_buildings')
                && DB::table('audited_buildings')->where('globalid', $value)->exists();

            if (! $existsInBuildings && ! $existsInAuditedBuildings) {
                $fail(__('validation.exists', ['attribute' => $attribute]));
            }
        };
    }
}
