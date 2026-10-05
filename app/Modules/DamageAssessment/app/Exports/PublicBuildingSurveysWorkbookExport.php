<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PublicBuildingSurveysWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  array<int, string>  $buildingColumns
     * @param  array<int, string>  $unitColumns
     */
    public function __construct(
        protected Collection $surveys,
        protected array $buildingColumns = [],
        protected array $unitColumns = [],
    ) {}

    public function sheets(): array
    {
        return [
            new PublicBuildingSurveysExport($this->surveys, $this->buildingColumns),
            new PublicBuildingSurveyUnitsExport($this->surveys, $this->unitColumns),
        ];
    }
}
