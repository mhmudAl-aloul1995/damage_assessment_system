<?php

namespace App\Modules\HeksShelter\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyChoice extends Model
{
    public function question(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'question_id');
    }

    protected $table = 'heks_survey_choices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'enabled' => 'boolean', 'published_at' => 'datetime', 'position' => 'integer', 'section_order' => 'integer', 'exclusive' => 'boolean'];
    }
}
