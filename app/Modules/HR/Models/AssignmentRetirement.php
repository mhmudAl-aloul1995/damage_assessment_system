<?php

namespace App\Modules\HR\Models;

use App\Modules\HR\Models\Casts\DateOnly;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentRetirement extends AppendOnlyModel
{
    protected $table = 'hr_assignment_retirements';

    protected $fillable = ['shift_assignment_id', 'ends_on', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['ends_on' => DateOnly::class];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'shift_assignment_id');
    }
}
