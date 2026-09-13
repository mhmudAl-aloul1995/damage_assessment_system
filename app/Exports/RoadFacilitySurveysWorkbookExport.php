<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RoadFacilitySurveysWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  array<int, string>  $roadColumns
     * @param  array<int, string>  $itemColumns
     */
    public function __construct(
        protected Collection $surveys,
        protected array $roadColumns = [],
        protected array $itemColumns = [],
    ) {}

    public function sheets(): array
    {
        return [
            new RoadFacilitySurveysExport($this->surveys, $this->roadColumns),
            new RoadFacilitySurveyItemsExport($this->surveys, $this->itemColumns),
        ];
    }
}
