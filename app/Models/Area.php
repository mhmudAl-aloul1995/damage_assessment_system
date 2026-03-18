<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Area
 * 
 * @property int $id
 * @property string|null $type
 * @property string|null $field_val_en
 * @property string|null $field_val_ar
 *
 * @package App\Models
 */
class Area extends Model
{
	protected $table = 'areas';
	public $timestamps = false;

	protected $fillable = [
		'type',
		'field_val_en',
		'field_val_ar'
	];
}
