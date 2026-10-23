<?php

namespace App\Modules\HeksShelter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyRevision extends Model
{
    protected $table = 'heks_survey_revisions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'edit_version' => 'integer'];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class, 'revision_id')->orderBy('section_order')->orderBy('position')->orderBy('id');
    }

    public function author(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
