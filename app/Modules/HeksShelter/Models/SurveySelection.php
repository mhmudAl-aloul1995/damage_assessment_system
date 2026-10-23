<?php

namespace App\Modules\HeksShelter\Models;

use Illuminate\Database\Eloquent\Model;

class SurveySelection extends Model
{
    protected $table = 'heks_survey_selections';

    public $timestamps = false;

    protected $guarded = ['id'];
}
