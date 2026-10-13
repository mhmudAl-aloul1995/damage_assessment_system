<?php

namespace App\Modules\HR\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    protected $table = 'hr_attendance_report_exports';

    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;

    public function newEloquentBuilder($query): ReportExportBuilder
    {
        return new ReportExportBuilder($query);
    }

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new \LogicException('Attendance export request history cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return ['actor_id' => 'integer', 'filters' => 'array', 'employee_ids' => 'array', 'parts' => 'array', 'expires_at' => \App\Modules\HR\Models\Casts\UtcInstant::class];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
