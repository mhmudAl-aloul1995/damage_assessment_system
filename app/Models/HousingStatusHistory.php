<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class HousingStatusHistory
 *
 * @property int $id
 * @property int $housing_id
 * @property int $status_id
 * @property int|null $user_id
 * @property string|null $notes
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property HousingUnit $housing_unit
 * @property AssessmentStatus $assessment_status
 * @property User|null $user
 */
class HousingStatusHistory extends Model
{
    protected $table = 'housing_status_histories';

    protected $casts = [
        'housing_id' => 'int',
        'status_id' => 'int',
        'user_id' => 'int',
    ];

    protected $fillable = [
        'housing_id',
        'status_id',
        'user_id',
        'type',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $history): void {
            if (! is_string($history->type) || trim($history->type) === '') {
                throw new InvalidArgumentException('Housing status history type must not be empty.');
            }
        });
    }

    public function housing_unit(): BelongsTo
    {
        return $this->belongsTo(HousingUnit::class, 'housing_id', 'objectid');
    }

    public function assessment_status(): BelongsTo
    {
        return $this->belongsTo(AssessmentStatus::class, 'status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
