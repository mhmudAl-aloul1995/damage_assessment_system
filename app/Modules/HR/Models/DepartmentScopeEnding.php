<?php

namespace App\Modules\HR\Models;

class DepartmentScopeEnding extends AppendOnlyModel
{
    protected $table = 'hr_department_scope_endings';

    protected $fillable = ['scope_id', 'ends_on', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['ends_on' => \App\Modules\HR\Models\Casts\DateOnly::class];
    }
}
