<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentStatus extends Model
{
    protected $fillable = [
        'name',
        'label_en',
        'label_ar',
        'stage',
        'order_step',
    ];

    public function getColorAttribute(): string
    {
        return self::colorForName((string) $this->name);
    }

    public function getBadgeClassAttribute(): string
    {
        return 'badge badge-light-'.$this->color;
    }

    public function getLabelAttribute(): string
    {
        $primaryLabel = app()->getLocale() === 'ar'
            ? $this->label_en
            : $this->label_en;

        return $primaryLabel ?: ($this->label_en ?: ($this->label_ar ?: $this->name));
    }

    public function getBadgeHtmlAttribute(): string
    {
        return self::badgeHtmlFor($this->name, $this->label);
    }

    public static function colorForName(?string $name): string
    {
        return match (self::normalizeName($name)) {
            'pending' => 'secondary',
            'assigned_to_engineer' => 'info',
            'rejected_by_engineer' => 'danger',
            'accepted_by_engineer' => 'success',
            'need_review' => 'warning',
            'assigned_to_lawyer' => 'primary',
            'legal_notes' => 'warning',
            'accepted_by_lawyer' => 'success',
            'final_approval' => 'success',
            'undp_final_approve' => 'primary',
            'final_reject' => 'danger',
            default => 'light',
        };
    }

    public static function badgeClassForName(?string $name): string
    {
        if ($name == 'pending') {
            return 'badge badge-secondary';
        }

        return 'badge badge-light-'.self::colorForName($name);
    }

    public static function badgeHtmlFor(?string $name, ?string $label = null): string
    {
        return '<span class="'.e(self::badgeClassForName($name)).' fw-bold px-4 py-3">'
            .e($label ?: '-')
            .'</span>';
    }

    /**
     * @return list<string>
     */
    public static function aliasesForName(?string $name): array
    {
        return match (self::normalizeName($name)) {
            'assigned_to_engineer' => ['assigned_to_engineer', 'assigned_to_engineer'],
            'assigned_to_lawyer' => ['assigned_to_lawyer', 'assigned_to_lawyer'],
            'final_reject' => ['final_reject', 'final_rejected'],
            default => [strtolower(trim((string) $name))],
        };
    }

    private static function normalizeName(?string $name): string
    {
        $name = strtolower(trim((string) $name));

        return match ($name) {
            'assigned_to_engineer' => 'assigned_to_engineer',
            'assigned_to_lawyer' => 'assigned_to_lawyer',
            'final_rejected' => 'final_reject',
            default => $name,
        };
    }

    public function buildingStatuses(): HasMany
    {
        return $this->hasMany(BuildingStatus::class, 'status_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(BuildingStatusHistory::class, 'status_id');
    }
}
