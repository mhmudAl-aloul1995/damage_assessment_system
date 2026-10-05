<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfAuditStatus extends Model
{
    protected $guarded = [];

    public function getColorAttribute(): string
    {
        return match ($this->name) {
            'assigned' => 'info',
            'accepted', 'final_approval' => 'success',
            'rejected' => 'danger',
            'need_review' => 'warning',
            default => 'light',
        };
    }

    public function getBadgeClassAttribute(): string
    {
        return 'badge badge-light-'.$this->color;
    }

    public function getLabelAttribute(): string
    {
        return app()->getLocale() === 'ar'
            ? ($this->label_ar ?: $this->label_en ?: $this->name)
            : ($this->label_en ?: $this->label_ar ?: $this->name);
    }
}
