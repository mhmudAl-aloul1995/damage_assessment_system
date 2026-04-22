<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $table = 'users';

    protected $connection = 'mysql';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'avatar',
        'address',
        'email_verified_at',
        'password',
        'remember_token',
        'id_no',
        'contract_type',
        'contract_start_date',
        'region',
        'name_en',
        'username_arcgis',
        'telegram_chat_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getConnectionName(): ?string
    {
        if (app()->environment('testing')) {
            return config('database.default');
        }

        return $this->connection;
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'email' => 'string',
            'phone' => 'string',
            'avatar' => 'string',
            'address' => 'string',
            'username_arcgis' => 'string',
            'telegram_chat_id' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'string',
            'remember_token' => 'string',
            'contract_start_date' => 'date',
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
        return $this->hasMany(Attendance::class, 'user_id');
    }

    public function committeeMembers()
    {
        return $this->hasMany(CommitteeMember::class);
    }

    public function managedCommitteeDecisions()
    {
        return $this->hasMany(CommitteeDecision::class, 'committee_manager_id');
    }

    public function telegramIntegrations()
    {
        return $this->hasMany(TelegramIntegration::class);
    }
}
