<?php

namespace App\Modules\HeksShelter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyQuestion extends Model
{
    protected $table = 'heks_survey_questions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'enabled' => 'boolean', 'published_at' => 'datetime', 'position' => 'integer', 'section_order' => 'integer', 'exclusive' => 'boolean'];
    }

    public function choices(): HasMany
    {
        return $this->hasMany(SurveyChoice::class, 'question_id')->orderBy('position')->orderBy('id');
    }

    public function revision(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SurveyRevision::class, 'revision_id');
    }
}
