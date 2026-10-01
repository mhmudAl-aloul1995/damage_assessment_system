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
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['damage_status.in' => __('sector-overview.invalid_damage_status'), 'after_id.integer' => __('sector-overview.invalid_cursor')];
    }
}
