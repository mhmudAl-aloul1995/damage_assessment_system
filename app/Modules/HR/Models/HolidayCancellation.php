<?php

namespace App\Modules\HR\Models;

use App\Modules\HR\Models\Casts\DateOnly;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HolidayCancellation extends AppendOnlyModel
{
    protected $table = 'hr_holiday_cancellations';

    protected $fillable = ['holiday_id', 'cancelled_from', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['cancelled_from' => DateOnly::class];
    }

    public function holiday(): BelongsTo
    {
        return $this->belongsTo(Holiday::class);
    }
}
