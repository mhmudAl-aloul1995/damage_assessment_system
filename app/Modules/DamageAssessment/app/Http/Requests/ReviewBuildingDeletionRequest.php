<?php

namespace App\Http\Requests;

use App\Enums\BuildingDeletionStatus;
use App\Models\BuildingDeletionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewBuildingDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deletionRequest = $this->route('buildingDeletionRequest');

        if (! $deletionRequest instanceof BuildingDeletionRequest) {
            return false;
        }

        return match ($deletionRequest->status) {
            BuildingDeletionStatus::PendingTeamLeaderReview => $this->user()?->can('reviewTeamLeader', $deletionRequest) ?? false,
            BuildingDeletionStatus::PendingAreaManagerReview => $this->user()?->can('reviewAreaManager', $deletionRequest) ?? false,
            BuildingDeletionStatus::PendingGisReview => $this->user()?->can('reviewGis', $deletionRequest) ?? false,
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject', 'return'])],
            'review_notes' => ['required', 'string', 'min:5'],
        ];
    }
}
