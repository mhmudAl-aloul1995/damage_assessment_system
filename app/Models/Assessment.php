<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Assessment
 * 
 * @property int $id
 * @property string|null $name
 * @property string|null $label
 * @property string|null $hint
 *
 * @package App\Models
 */
class Assessment extends Model
{
	protected $table = 'assessments';
	public $timestamps = false;

	protected $fillable = [
		'name',
		'label',
		'hint',
		'criteria',
	];
}
