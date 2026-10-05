<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\services\SectorOverviewService;
use App\Support\Navigation\SectorNavigation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectorOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && collect(SectorNavigation::forUser((string) $this->route('sector'), $this->user()))->contains('key', 'overview');
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'municipality' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'damage_status' => ['nullable', 'string', Rule::in(app(SectorOverviewService::class)->damageBuckets((string) $this->route('sector')))],
            'after_id' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
            'metric' => ['nullable', 'string', Rule::prohibitedIf($this->routeIs('sector-overview.show')), Rule::in(['total', 'completed', 'action_required', 'approved'])],
            'field_completion' => ['nullable', 'string', Rule::in(['completed', 'not_completed'])],
            'audit_status' => ['nullable', 'string', Rule::in(app(SectorOverviewService::class)->auditBuckets((string) $this->route('sector')))],
            'west' => ['nullable', 'required_with:south,east,north', 'numeric', 'between:-180,180'],
            'east' => ['nullable', 'required_with:west,south,north', 'numeric', 'between:-180,180', 'gte:west'],
            'south' => ['nullable', 'required_with:west,east,north', 'numeric', 'between:-90,90'],
            'north' => ['nullable', 'required_with:west,east,south', 'numeric', 'between:-90,90', 'gte:south'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['damage_status.in' => __('sector-overview.invalid_damage_status'), 'after_id.integer' => __('sector-overview.invalid_cursor'),
            'audit_status.in' => __('sector-overview.invalid_audit_status'), 'field_completion.in' => __('sector-overview.invalid_field_completion'),
            '*.required_with' => __('sector-overview.invalid_extent'), '*.gte' => __('sector-overview.invalid_extent')];
    }
}
