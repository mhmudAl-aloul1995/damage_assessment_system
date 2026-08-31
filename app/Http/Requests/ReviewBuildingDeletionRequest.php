<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewBuildingDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deletionRequest = $this->route('buildingDeletionRequest');

        return $deletionRequest instanceof \App\Models\BuildingDeletionRequest
            && ($this->user()?->can('reviewGis', $deletionRequest) ?? false);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject', 'return'])],
            'gis_notes' => ['required', 'string', 'min:5'],
            'reviewed_all_records' => ['required_if:decision,approve', 'accepted'],
            'understands_snapshot_gate' => ['required_if:decision,approve', 'accepted'],
        ];
    }
}
