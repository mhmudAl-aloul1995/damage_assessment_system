<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PublicBuildingSurveysWorkbookExport implements WithMultipleSheets
{
    public function __construct(protected Collection $surveys) {}

    public function sheets(): array
    {
        return [
            new PublicBuildingSurveysExport($this->surveys),
            new PublicBuildingSurveyUnitsExport($this->surveys),
        ];
    }
}
