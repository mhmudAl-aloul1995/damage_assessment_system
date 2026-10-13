<?php

namespace App\Modules\HR\Models;

use Database\Factories\HrJobTitleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JobTitle extends CatalogModel
{
    use HasFactory;

    protected $table = 'hr_job_titles';

    protected static function newFactory(): HrJobTitleFactory
    {
        return HrJobTitleFactory::new();
    }
}
