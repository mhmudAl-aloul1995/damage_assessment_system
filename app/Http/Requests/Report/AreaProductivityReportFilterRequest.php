<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class AreaProductivityReportFilterRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const MULTIPLE_FILTERS = [
        'governorate',
        'municipalitie',
        'neighborhood',
        'zone_code',
        'assignedto',
    ];

    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'governorate' => ['nullable', 'array'],
            'governorate.*' => ['string', 'max:255'],
            'municipalitie' => ['nullable', 'array'],
            'municipalitie.*' => ['string', 'max:255'],
            'neighborhood' => ['nullable', 'array'],
            'neighborhood.*' => ['string', 'max:255'],
            'zone_code' => ['nullable', 'array'],
            'zone_code.*' => ['string', 'max:255'],
            'assignedto' => ['nullable', 'array'],
            'assignedto.*' => ['string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $filters = [];

        foreach (self::MULTIPLE_FILTERS as $filter) {
            $value = $this->input($filter);

            if ($value === null || $value === '') {
                continue;
            }

            $filters[$filter] = collect(is_array($value) ? $value : [$value])
                ->map(fn (mixed $item): string => trim((string) $item))
                ->filter()
                ->values()
                ->all();
        }

        $this->merge($filters);
    }
}
