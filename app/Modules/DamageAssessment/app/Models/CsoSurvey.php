<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPhaseScope;
use App\Support\Forms\CsoSurveyLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class CsoSurvey extends Model
{
    use HasPhaseScope;

    protected $guarded = [];

    protected $appends = [
        'assignedto',
    ];

    public function getRouteKeyName(): string
    {
        return 'globalid';
    }

    protected function casts(): array
    {
        return [
            'damage_date' => 'date',
            'creationdate' => 'datetime',
            'editdate' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function infAuditStatus(): HasOne
    {
        return $this->hasOne(CsoSurveyAuditStatus::class)->latestOfMany();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(CsoAuditLog::class, 'cso_survey_id');
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(CsoSurveyOrganization::class, 'parentglobalid', 'globalid');
    }

    public function units(): HasMany
    {
        return $this->hasMany(CsoSurveyUnit::class, 'parentglobalid', 'globalid');
    }

    /** @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, CsoSurveyUnit>> */
    public function unitsGroupedByOrganization(): Collection
    {
        $normalize = fn (mixed $value): string => is_scalar($value) ? strtolower(trim(trim((string) $value), '{}')) : '';
        $organizations = $this->organizations->groupBy(
            fn (CsoSurveyOrganization $organization): string => $normalize($organization->globalid),
        );

        return CsoSurveyUnit::query()->forSurvey($this)->orderBy('objectid')->get()
            ->groupBy(function (CsoSurveyUnit $unit) use ($organizations, $normalize): string {
                $sourceParent = CsoSurveyLayout::value((object) ['raw_payload' => $unit->raw_payload], 'parentglobalid');
                $matches = collect([$sourceParent, $unit->parentglobalid])->map($normalize)
                    ->filter(fn (string $parent): bool => $parent !== '' && $organizations->has($parent))
                    ->unique()->values();

                if ($matches->count() !== 1 || $organizations[$matches->first()]->count() !== 1) {
                    return 'unassigned';
                }

                return 'organization-'.$organizations[$matches->first()]->first()->id;
            });
    }

    public function infAuditAssignment(): HasOne
    {
        return $this->hasOne(InfAuditAssignment::class, 'globalid', 'globalid')
            ->where('type', 'cso_survey');
    }

    public function getAssignedToAttribute(?string $value): ?string
    {
        return $value ?: ($this->attributes['assignedto'] ?? null);
    }
}
