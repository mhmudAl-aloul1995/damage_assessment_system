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
class Filter extends Model
{
	protected $table = 'filters';
	public $timestamps = false;

	protected $fillable = [
		'list_name',
		'list_name_arabic',
		'name',
		'label'
	];
}
