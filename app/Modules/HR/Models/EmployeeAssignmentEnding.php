<?php

namespace App\Modules\HR\Models;

class EmployeeAssignmentEnding extends AppendOnlyModel
{
    protected $table = 'hr_employee_assignment_endings';

    protected $fillable = ['assignment_id', 'ends_on', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['ends_on' => \App\Modules\HR\Models\Casts\DateOnly::class];
    }
}
