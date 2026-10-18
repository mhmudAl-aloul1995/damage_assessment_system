<?php

namespace App\Modules\Reh\Models;

use Illuminate\Database\Eloquent\Model;

class RehSyncIssue extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['source' => 'array', 'resolved_at' => 'datetime'];
    }

    protected static function newFactory(): \Database\Factories\RehSyncIssueFactory
    {
        return \Database\Factories\RehSyncIssueFactory::new();
    }
}
