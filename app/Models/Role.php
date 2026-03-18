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
class Role extends Model
{
	protected $table = 'roles';
	public $timestamps = false;

	
}
