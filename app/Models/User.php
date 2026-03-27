<?php

namespace App\Models;

// 1. Add this import at the top
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;

class User extends Authenticatable
{
    // 2. Add HasFactory inside the class definition
    use HasApiTokens, HasFactory, Notifiable, HasRoles;
    protected $table = 'users';

    /**
     * @var string
     */
    protected $connection = 'mysql';

    protected $primaryKey = 'id';

    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'avatar',
        'address',
        'role',
        'email_verified_at',
        'password',
        'remember_token',
         'id_no',
    'contract_type',
    'name_en',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [];

    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'email' => 'string',
            'phone' => 'string',
            'avatar' => 'string',
            'address' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'string',
            'remember_token' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function buildingStatuses()
    {
        return $this->hasMany(BuildingStatus::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(BuildingStatusHistory::class);
    }
    
    public function attendances()
{
    return $this->hasMany(Attendance::class);
}
}
