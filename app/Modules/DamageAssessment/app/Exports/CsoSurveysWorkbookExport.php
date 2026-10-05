<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CsoSurveysWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  array<int, string>  $surveyColumns
     * @param  array<int, string>  $organizationColumns
     * @param  array<int, string>  $unitColumns
     */
    public function __construct(
        protected Collection $surveys,
        protected array $surveyColumns = [],
        protected array $organizationColumns = [],
        protected array $unitColumns = [],
    ) {}

    public function sheets(): array
    {
        return [
            new CsoSurveysExport($this->surveys, $this->surveyColumns),
            new CsoSurveyOrganizationsExport($this->surveys, $this->organizationColumns),
            new CsoSurveyUnitsExport($this->surveys, $this->unitColumns),
        ];
    }
}
