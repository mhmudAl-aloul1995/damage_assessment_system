<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class HousingStatus
 * 
 * @property int $id
 * @property int $housing_id
 * @property int $status_id
 * @property int|null $user_id
 * @property string $type
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property HousingUnit $housing_unit
 * @property AssessmentStatus $assessment_status
 * @property User|null $user
 *
 * @package App\Models
 */
class HousingStatus extends Model
{
	protected $table = 'housing_statuses';

	protected $casts = [
		'housing_id' => 'int',
		'status_id' => 'int',
		'user_id' => 'int'
	];

	protected $fillable = [
		'housing_id',
		'status_id',
		'user_id',
		'type',
		'notes'
	];

	public function housing_unit()
	{
		return $this->belongsTo(HousingUnit::class, 'housing_id','objectid');
	}

	public function assessment_status()
	{
		return $this->belongsTo(AssessmentStatus::class, 'status_id');
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
