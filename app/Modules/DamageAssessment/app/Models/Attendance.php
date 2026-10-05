<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Attendance
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property bool $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property User $user
 */
class Attendance extends Model
{
    protected $table = 'attendances';

    protected $casts = [
        'user_id' => 'int',
        'date' => 'datetime',
        'status' => 'bool',
        'updated_by' => 'int',
    ];

    protected $fillable = [
        'user_id',
        'date',
        'status',
        'updated_by',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
