<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\SectorOverviewRequest;

class DamageInquiryRequest extends SectorOverviewRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [...parent::rules(), 'search' => ['nullable', 'string', 'max:150'], 'track' => ['nullable', 'in:engineering,legal']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(), 'search.string' => 'أدخل نصًا للبحث.', 'search.max' => 'نص البحث يجب ألا يتجاوز 150 حرفًا.', 'track.in' => 'مسار التدقيق غير صالح.'];
    }

    /** @return array<string, mixed> */
    public function inquiryFilters(): array
    {
        $filters = [...$this->validated(), '_inquiry' => true];
        $allowedPhases = collect($this->user()->allowed_phase_numbers ?? [])
            ->map(fn (mixed $phase): int => (int) $phase)->filter(fn (int $phase): bool => $phase > 0)->values()->all();
        if ($allowedPhases !== []) {
            $filters['_allowed_phases'] = $allowedPhases;
        }

        return $filters;
    }
}
