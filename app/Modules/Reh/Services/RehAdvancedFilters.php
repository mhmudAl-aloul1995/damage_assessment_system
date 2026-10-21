<?php

namespace App\Modules\Reh\Services;

use App\Modules\Reh\Models\RehAuditLog;
use App\Modules\Reh\Models\RehUnit;
use Illuminate\Database\Eloquent\Builder;

class RehAdvancedFilters
{
    public const SCORE_FILTERS = ['score', 'score_op', 'score_min', 'score_max', 'engineering', 'engineering_op', 'engineering_min', 'engineering_max', 'evaluation', 'evaluation_op', 'priority_op'];

    public const SENSITIVE_FILTERS = ['social', 'social_op', 'social_min', 'social_max', 'household', 'household_op', 'household_min', 'household_max', 'hosting', 'hosting_op', 'owner_disability', 'owner_disability_op', 'family_chronic', 'family_chronic_op'];

    public const UNIT_FILTERS = ['status', 'status_op', 'priority', 'decision', 'decision_op', 'edits', 'edits_op', ...self::SCORE_FILTERS, ...self::SENSITIVE_FILTERS];

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
        'score' => 'الدرجة النهائية', 'engineering' => 'الدرجة الهندسية', 'social' => 'الدرجة الاجتماعية', 'household' => 'أفراد أسرة المالك', 'visit' => 'تاريخ الزيارة',
    ];

    public static function conditions(): array
    {
        $conditions = [];
        foreach (['q' => 'text', 'survey_status' => 'choice', 'status' => 'choice', 'priority' => 'choice', 'governorate' => 'choice', 'neighborhood' => 'choice', 'engineer' => 'choice', 'building_old_objectid' => 'text', 'decision' => 'choice', 'evaluation' => 'choice', 'edits' => 'choice', 'hosting' => 'choice', 'owner_disability' => 'choice', 'family_chronic' => 'choice', 'household' => 'integer', 'score' => 'integer', 'engineering' => 'integer', 'social' => 'integer', 'visit' => 'date'] as $key => $type) {
            $operators = RehFilterOperators::choices($type);
            if (in_array($key, ['survey_status', 'status', 'priority', 'decision', 'evaluation', 'edits'], true)) {
                $operators = array_intersect_key($operators, array_flip(['is', 'is_not']));
            } elseif ($key === 'q') {
                $operators = array_intersect_key($operators, array_flip(['contains', 'is', 'not_contains']));
            }
            $conditions[$key] = ['type' => $type, 'field' => $key, 'default' => $key === 'building_old_objectid' ? 'is' : RehFilterOperators::default($type), 'operators' => $operators];
        }

        return $conditions;
    }

    public function conditionRules(array $input): array
    {
        $rules = [];
        foreach (self::conditions() as $key => $definition) {
            $operator = $input[$key.'_op'] ?? $definition['default'];
            $presence = in_array($operator, ['blank', 'not_blank'], true);
            $rules[$key.'_op'] = ['nullable', 'string', \Illuminate\Validation\Rule::in(array_keys($definition['operators']))];
            if (in_array($definition['type'], ['integer', 'date'], true)) {
                $maximum = ['score' => 100, 'engineering' => 40, 'social' => 60][$key] ?? 1000000;
                $valueRules = $definition['type'] === 'date' ? ['date_format:Y-m-d'] : ['integer', 'min:0', 'max:'.$maximum];
                $rules[$key] = ['nullable', ...$valueRules, \Illuminate\Validation\Rule::prohibitedIf($presence || $operator === 'between')];
                foreach (['min', 'max'] as $bound) {
                    $name = $key === 'visit' ? ($bound === 'min' ? 'visit_from' : 'visit_to') : $key.'_'.$bound;
                    $rules[$name] = ['nullable', ...$valueRules, \Illuminate\Validation\Rule::prohibitedIf($presence || $operator !== 'between')];
                }
                $minimum = $key === 'visit' ? 'visit_from' : $key.'_min';
                $maximumName = $key === 'visit' ? 'visit_to' : $key.'_max';
                if (isset($input[$minimum]) && $input[$minimum] !== '') {
                    $rules[$maximumName][] = ($definition['type'] === 'date' ? 'after_or_equal:' : 'gte:').$minimum;
                }
            } else {
                $rules[$key] = [\Illuminate\Validation\Rule::prohibitedIf($presence)];
            }
            if (! empty($input[$key.'_op']) && ! $presence) {
                $requiredKey = $operator === 'between' ? ($key === 'visit' ? 'visit_from' : $key.'_min') : $key;
                $maximumName = $key === 'visit' ? 'visit_to' : $key.'_max';
                $rules[$requiredKey][] = \Illuminate\Validation\Rule::requiredIf($operator !== 'between' || ! isset($input[$maximumName]) || $input[$maximumName] === '');
            }
        }

        return $rules;
    }

    public function stored(Builder $query, string $column, array $filters, string $key): void
    {
        if (RehFilterOperators::active($filters, $key)) {
            $definition = self::conditions()[$key];
            app(RehFilterOperators::class)->apply($query, $query->qualifyColumn($column), $definition, [...$filters, $key.'_op' => $filters[$key.'_op'] ?? $definition['default']], $key);
        }
    }

    public function search(Builder $query, array $filters, bool $building = false): Builder
    {
        $value = trim($filters['q'] ?? '');
        if ($value === '') {
            return $query;
        }
        $operator = $filters['q_op'] ?? 'contains';
        $method = $operator === 'not_contains' ? 'whereNot' : 'where';
        if ($building && $operator === 'not_contains') {
            return $query->where(function (Builder $matches) use ($filters, $value): void {
                $matches->whereHas('units', fn (Builder $units) => $this->search($units, $filters));
                $matches->orWhere(function (Builder $empty) use ($value): void {
                    $empty->whereDoesntHave('units')->whereNot(function (Builder $names) use ($value): void {
                        foreach (['name', 'reference_id'] as $column) {
                            $names->orWhere(function (Builder $part) use ($column, $value): void {
                                $part->whereNotNull($column);
                                $this->stored($part, $column, ['q' => $value, 'q_op' => 'contains'], 'q');
                            });
                        }
                    });
                });
            });
        }

        return $query->$method(function (Builder $matches) use ($value, $operator, $building): void {
            foreach ($building ? ['name', 'reference_id'] : ['code', 'owner_name', 'reference_id'] as $column) {
                $matches->orWhere(function (Builder $part) use ($column, $value, $operator): void {
                    $part->whereNotNull($column);
                    $this->stored($part, $column, ['q' => $value, 'q_op' => $operator === 'is' ? 'is' : 'contains'], 'q');
                });
            }
            if ($building) {
                $matches->orWhereHas('units', fn (Builder $units) => $this->search($units, ['q' => $value, 'q_op' => $operator === 'is' ? 'is' : 'contains']));
            } else {
                $matches->orWhereHas('building', function (Builder $parent) use ($value, $operator): void {
                    $parent->where(function (Builder $names) use ($value, $operator): void {
                        foreach (['name', 'reference_id'] as $column) {
                            $names->orWhere(function (Builder $part) use ($column, $value, $operator): void {
                                $part->whereNotNull($column);
                                $this->stored($part, $column, ['q' => $value, 'q_op' => $operator === 'is' ? 'is' : 'contains'], 'q');
                            });
                        }
                    });
                });
                if (auth()->user()->can('reh.sensitive')) {
                    $matches->orWhere(fn (Builder $identity) => $identity->whereNotNull('owner_identity')->where('owner_identity', $value));
                }
            }
        });
    }

    public function buildings(Builder $query, array $filters): Builder
    {
        foreach (['neighborhood' => 'neighborhood', 'governorate' => 'governorate', 'engineer' => 'engineer', 'building_old_objectid' => 'reference_id'] as $key => $column) {
            $this->stored($query, $column, $filters, $key);
        }
        $this->stored($query, 'visit_date', [...$filters, 'visit_min' => $filters['visit_from'] ?? null, 'visit_max' => $filters['visit_to'] ?? null], 'visit');

        return app(RehSurveyFilters::class)->apply($query, $filters, 'building');
    }

    public function units(Builder $query, array $filters): Builder
    {
        app(RehSurveyFilters::class)->apply($query, $filters, 'unit');
        foreach (['status' => 'workflow_status', 'decision' => 'decision', 'hosting' => 'hosts_other_households', 'owner_disability' => 'owner_has_disability', 'household' => 'household_size'] as $key => $column) {
            $this->stored($query, $column, $filters, $key);
        }
        if (RehFilterOperators::active($filters, 'family_chronic')) {
            $operator = $filters['family_chronic_op'] ?? 'is';
            if (in_array($operator, ['blank', 'not_blank'], true)) {
                $query->{$operator === 'blank' ? 'whereNull' : 'whereNotNull'}('household_chronic_count');
            } else {
                if ($operator === 'is_not' && ! RehFilterChoices::contains($filters['family_chronic'], '__missing')) {
                    $query->whereNotNull('household_chronic_count');
                }
                $query->{$operator === 'is_not' ? 'whereNot' : 'where'}(function (Builder $q) use ($filters): void {
                    foreach (RehFilterChoices::values($filters['family_chronic']) as $value) {
                        $q->orWhere(fn (Builder $part) => $value === '__missing' ? $part->whereNull('household_chronic_count') : $part->whereNotNull('household_chronic_count')->where('household_chronic_count', $value === 'yes' ? '>' : '=', 0));
                    }
                });
            }
        }
        if (($filters['edits_op'] ?? 'is') === 'is_not' && count(RehFilterChoices::values($filters['edits'] ?? null)) === 2) {
            $query->whereRaw('1 = 0');
        } elseif (count(RehFilterChoices::values($filters['edits'] ?? null)) === 1) {
            $logs = RehAuditLog::query()->where('status', 'completed')->when(! auth()->user()->can('reh.sensitive'), fn (Builder $q) => $q->where('sensitive', false));
            $query->where(function (Builder $q) use ($logs, $filters): void {
                $yes = RehFilterChoices::contains($filters['edits'], 'yes') !== (($filters['edits_op'] ?? 'is') === 'is_not');
                $method = $yes ? 'whereIn' : 'whereNotIn';
                $orMethod = $yes ? 'orWhereIn' : 'whereNotIn';
                $q->$method('id', (clone $logs)->where('record_kind', 'unit')->select('record_id'));
                $q->$orMethod('building_id', (clone $logs)->where('record_kind', 'building')->select('record_id'));
            });
        }
        $computed = array_intersect_key($filters, array_flip(['priority', 'social', 'social_op', 'social_min', 'social_max', ...self::SCORE_FILTERS]));
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
        if (array_filter(array_intersect_key($filters, array_flip(['priority', 'social', 'social_op', 'social_min', 'social_max', ...self::SCORE_FILTERS])), fn ($value): bool => $value !== '' && $value !== null)) {
            $score = app(RehScoring::class)->calculate($unit);
            if (! empty($filters['priority']) && RehFilterChoices::contains($filters['priority'], $score['priority'] ?? 'unclassified') === (($filters['priority_op'] ?? 'is') === 'is_not')) {
                return false;
            }
            if (! empty($filters['evaluation']) && RehFilterChoices::contains($filters['evaluation'], $score['total'] === null ? 'incomplete' : 'complete') === (($filters['evaluation_op'] ?? 'is') === 'is_not')) {
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
        $operator = $filters[$prefix.'_op'] ?? 'between';
        if ($operator === 'blank' || $operator === 'not_blank') {
            return ($value === null) === ($operator === 'blank');
        }
        if ($operator !== 'between') {
            if (! isset($filters[$prefix]) || $filters[$prefix] === '') {
                return true;
            }
            if ($value === null) {
                return false;
            }
            $limit = (int) $filters[$prefix];

            return match ($operator) {
                'is' => $value === $limit, 'is_not' => $value !== $limit, 'gt' => $value > $limit, 'gte' => $value >= $limit, 'lt' => $value < $limit, 'lte' => $value <= $limit,
            };
        }
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
            if (isset($filters[$key.'_op'])) {
                $summary[$label] = RehFilterOperators::LABELS[$filters[$key.'_op']].' · '.$summary[$label];
            }
        }
        foreach (self::conditions() as $key => $definition) {
            $operator = $filters[$key.'_op'] ?? null;
            if (in_array($operator, ['blank', 'not_blank'], true) || in_array($key, ['score', 'engineering', 'social', 'household', 'visit'], true) && isset($filters[$key])) {
                $label = self::LABELS[$key] ?? ['score' => 'الدرجة النهائية', 'engineering' => 'الدرجة الهندسية', 'social' => 'الدرجة الاجتماعية', 'household' => 'أفراد أسرة المالك', 'visit' => 'تاريخ الزيارة'][$key];
                $summary[$label] = $definition['operators'][$operator].(isset($filters[$key]) ? ' · '.$filters[$key] : '');
            }
        }

        return [...$summary, ...app(RehSurveyFilters::class)->summary($filters)];
    }
}
