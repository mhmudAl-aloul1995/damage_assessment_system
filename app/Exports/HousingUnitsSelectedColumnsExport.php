<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\AuditedHousingUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class HousingUnitsSelectedColumnsExport implements FromQuery, WithHeadings, WithMapping, WithStrictNullComparison
{
    /**
     * @param  Builder<AuditedHousingUnit>  $query
     * @param  array<int, string>  $columns
     * @param  Collection<string, object>  $assessmentHints
     */
    public function __construct(
        private readonly Builder $query,
        private readonly array $columns,
        private readonly Collection $assessmentHints,
    ) {}

    /**
     * @return Builder<AuditedHousingUnit>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_map(function (string $column): string {
            $assessment = $this->assessmentHints->get($column);
            $heading = trim((string) ($assessment?->hint ?: $assessment?->label ?: ''));

            return $heading !== '' ? $heading : str($column)->replace('_', ' ')->title()->toString();
        }, $this->columns);
    }

    /**
     * @return array<int, mixed>
     */
    public function map(mixed $row): array
    {
        return array_map(fn (string $column): mixed => $row->{$column}, $this->columns);
    }
}
