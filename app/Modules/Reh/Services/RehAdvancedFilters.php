<?php

namespace App\Modules\Reh\Services;

use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehUnit;
use Illuminate\Database\Eloquent\Builder;

class RehAdvancedFilters
{
    public const SCORE_FILTERS = ['score_min', 'score_max', 'engineering_min', 'engineering_max', 'evaluation'];

    public const SENSITIVE_FILTERS = ['social_min', 'social_max', 'household_min', 'household_max', 'hosting', 'owner_disability', 'family_chronic'];

    public const UNIT_FILTERS = ['status', 'priority', 'decision', 'edits', ...self::SCORE_FILTERS, ...self::SENSITIVE_FILTERS];

    public const LABELS = [
        'q' => 'البحث', 'survey_status' => 'حالة الاستبيان', 'status' => 'التدقيق', 'priority' => 'الأولوية',
        'governorate' => 'المحافظة', 'neighborhood' => 'الحي', 'engineer' => 'المهندس',
        'building_old_objectid' => 'معرّف حصر المبنى', 'visit_from' => 'الزيارة من', 'visit_to' => 'الزيارة إلى',
        'score_min' => 'النهائي من', 'score_max' => 'النهائي إلى',
        'engineering_min' => 'الهندسي من', 'engineering_max' => 'الهندسي إلى',
        'social_min' => 'الاجتماعي من', 'social_max' => 'الاجتماعي إلى',
        'evaluation' => 'اكتمال التقييم', 'decision' => 'القرار', 'edits' => 'تعديلات الحقول',
        'household_min' => 'أفراد أسرة المالك من', 'household_max' => 'أفراد أسرة المالك إلى',
        'hosting' => 'أسر أخرى في الوحدة', 'owner_disability' => 'إعاقة المالك', 'family_chronic' => 'أمراض مزمنة بأسرة المالك',
    ];

    public function buildings(Builder $query, array $filters): Builder
    {
        $query->when($filters['neighborhood'] ?? null, fn (Builder $q, $value) => $q->whereIn('neighborhood', RehFilterChoices::values($value)));
        $query->when($filters['visit_from'] ?? null, fn (Builder $q, string $value) => $q->where('visit_date', '>=', $value));
        $query->when($filters['visit_to'] ?? null, fn (Builder $q, string $value) => $q->where('visit_date', '<=', $value));

        return app(RehSurveyFilters::class)->apply($query, $filters, 'building');
    }

    public function units(Builder $query, array $filters): Builder
    {
        app(RehSurveyFilters::class)->apply($query, $filters, 'unit');
        $query->when($filters['decision'] ?? null, fn (Builder $q, $value) => $q->whereIn('decision', RehFilterChoices::values($value)));
        foreach (['hosting' => 'hosts_other_households', 'owner_disability' => 'owner_has_disability'] as $filter => $field) {
            $query->when($filters[$filter] ?? null, fn (Builder $q, $value) => RehFilterChoices::apply($q, $field, $value));
        }
        foreach (['min' => '>=', 'max' => '<='] as $bound => $operator) {
            if (isset($filters['household_'.$bound]) && $filters['household_'.$bound] !== '') {
                $query->where('household_size', $operator, $filters['household_'.$bound]);
            }
        }
        if (! empty($filters['family_chronic'])) {
            $query->where(function (Builder $q) use ($filters): void {
                foreach (RehFilterChoices::values($filters['family_chronic']) as $value) {
                    $q->orWhere(fn (Builder $part) => $value === '__missing' ? $part->whereNull('household_chronic_count') : $part->where('household_chronic_count', $value === 'yes' ? '>' : '=', 0));
                }
            });
        }
        if (count(RehFilterChoices::values($filters['edits'] ?? null)) === 1) {
            $logs = RehAuditLog::query()->where('status', 'completed')->when(! auth()->user()->can('reh.sensitive'), fn (Builder $q) => $q->where('sensitive', false));
            $query->where(function (Builder $q) use ($logs, $filters): void {
                $yes = RehFilterChoices::contains($filters['edits'], 'yes');
                $method = $yes ? 'whereIn' : 'whereNotIn';
                $orMethod = $yes ? 'orWhereIn' : 'whereNotIn';
                $q->$method('id', (clone $logs)->where('record_kind', 'unit')->select('record_id'));
                $q->$orMethod('building_id', (clone $logs)->where('record_kind', 'building')->select('record_id'));
            });
        }
        $computed = array_intersect_key($filters, array_flip(['priority', 'social_min', 'social_max', ...self::SCORE_FILTERS]));
        if (count(array_filter($computed, fn ($value): bool => $value !== null && $value !== '')) > 0) {
            $ids = [];
            foreach ((clone $query)->lazyById(200) as $unit) {
                if ($this->matches($unit, $filters)) {
                    $ids[] = $unit->id;
                }
            }
            $query->whereIntegerInRaw('id', $ids);
        }

        return $query;
    }

    private function matches(RehUnit $unit, array $filters): bool
    {
        if (array_filter(array_intersect_key($filters, array_flip(['priority', 'social_min', 'social_max', ...self::SCORE_FILTERS])), fn ($value): bool => $value !== '' && $value !== null)) {
            $score = app(RehScoring::class)->calculate($unit);
            if (! empty($filters['priority']) && ! RehFilterChoices::contains($filters['priority'], $score['priority'] ?? 'unclassified')) {
                return false;
            }
            if (! empty($filters['evaluation']) && ! RehFilterChoices::contains($filters['evaluation'], $score['total'] === null ? 'incomplete' : 'complete')) {
                return false;
            }

            return $this->range($score['total'], $filters, 'score')
                && $this->range($score['engineering']['total'], $filters, 'engineering')
                && $this->range($score['social_total'], $filters, 'social');
        }

        return true;
    }

    private function range(mixed $value, array $filters, string $prefix): bool
    {
        foreach (['min', 'max'] as $bound) {
            $limit = $filters[$prefix.'_'.$bound] ?? null;
            if ($limit !== null && $limit !== '' && (! is_numeric($value) || $value < 0 || ($bound === 'min' ? $value < $limit : $value > $limit))) {
                return false;
            }
        }

        return true;
    }

    public function summary(array $filters): array
    {
        $survey = app(RehSurvey::class);
        $options = ['survey_status' => RehSurvey::FIELD_STATUSES, 'status' => RehSurvey::WORKFLOW, 'priority' => [...RehScoring::PRIORITIES, 'unclassified' => 'غير مصنفة'], 'evaluation' => ['complete' => 'مكتمل', 'incomplete' => 'بيانات ناقصة'], 'decision' => ['pending' => 'بانتظار القرار', 'selected' => 'مقبول مبدئيًا', 'excluded' => 'مستبعد']];
        $summary = [];
        foreach (self::LABELS as $key => $label) {
            $value = $filters[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $summary[$label] = implode('، ', array_map(fn ($choice): string => (string) ($options[$key][$choice] ?? (in_array($key, ['governorate', 'neighborhood', 'engineer'], true) ? $survey->value($key === 'engineer' ? 'engineer_name' : $key, $choice) : (['yes' => 'نعم', 'no' => 'لا', '__missing' => 'غير مسجل'][$choice] ?? (string) $choice))), RehFilterChoices::values($value)));
        }

        return [...$summary, ...app(RehSurveyFilters::class)->summary($filters)];
    }
}
