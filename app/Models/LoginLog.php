<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'username',
        'role',
        'ip_address',
        'user_agent',
        'is_success',
        'failure_reason',
        'logged_in_at',
        'logged_out_at',
    ];

    protected $casts = [
        'is_success' => 'boolean',
        'logged_in_at' => 'datetime',
        'logged_out_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}