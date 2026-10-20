<?php

namespace App\Modules\Reh\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class RehSurveyFilters
{
    public const GROUPS = [
        'building' => ['المبنى والفريق والخدمات', 'building', false, ['social_worker_name', 'building_floor_count', 'target_unit_count', 'area_m2', 'building_structurally_safe', 'building_uxo_present', 'building_dangerous_debris', 'building_referral_types', 'water_network_connected', 'sewage_network_connected', 'electricity_available']],
        'unit' => ['الوحدة والسلامة والموافقة', 'unit', false, ['floor_number', 'apartment_number', 'unit_area_sqm', 'accommodation_type', 'current_unit_occupancy', 'vacancy_reason', 'unit_needs_intervention', 'prior_project_assistance', 'consent_given', 'refusal_reason', 'consent_provider_role', 'unit_safe_to_enter', 'unit_structural_classification', 'unit_uxo_present', 'unit_dangerous_debris_present', 'demolished_units_above', 'unit_referral_types']],
        'owner' => ['المالك والمقيم والوثائق', 'unit', true, ['owner_sex', 'owner_age', 'owner_marital_status', 'owner_residence_status', 'ownership_proof_status', 'owner_has_chronic_disease', 'owner_disability_types', 'owner_pregnant_or_lactating', 'breadwinner_male_owner', 'breadwinner_female_owner', 'resident_role', 'resident_sex', 'resident_age', 'resident_marital_status', 'tenant_household_size']],
        'family' => ['أسرة المالك ومؤشرات الضعف', 'unit', true, ['household_males_under5', 'household_females_under5', 'household_males_age5_17', 'household_females_age5_17', 'household_males_age18_60', 'household_females_age18_60', 'household_males_over60', 'household_females_over60', 'household_pregnant_lactating', 'other_members_have_disability', 'other_vision_difficulty_count', 'other_hearing_difficulty_count', 'other_walking_difficulty_count', 'other_memory_difficulty_count', 'other_selfcare_diff_count', 'other_communication_diff_count', 'household_has_stable_income', 'nonfood_assistance_recency', 'shelter_assistance_recency', 'unaccompanied_child_count', 'household_protection_risks']],
        'other' => ['الأسر المستضافة والنازحة', 'unit', true, ['hosted_families_count', 'hosted_individuals_count', 'displaced_families_count', 'displaced_individuals_count', 'hosted_sex', 'hosted_age', 'hosted_marital_status', 'nonowner_has_chronic', 'nonowner_chronic_count', 'nonowner_has_disability', 'nonowner_disability_count', 'nonowner_vision_count', 'nonowner_hearing_count', 'nonowner_walking_count', 'nonowner_memory_count', 'nonowner_selfcare_count', 'nonowner_communication_count', 'nonowner_males_under5', 'nonowner_females_under5', 'nonowner_males_age5_17', 'nonowner_females_age5_17', 'nonowner_males_age18_60', 'nonowner_females_age18_60', 'nonowner_males_over60', 'nonowner_females_over60', 'nonowner_pregnant_lactating', 'nonowner_has_stable_income', 'nonowner_nfi_recency', 'nonowner_shelter_recency', 'nonowner_unaccompanied_count', 'nonowner_breadwinner_m', 'nonowner_breadwinner_f', 'nonowner_protection_risks']],
    ];

    public const DEPENDENCIES = [
        'sf_vacancy_reason' => ['sf_current_unit_occupancy', 'empty'],
        'sf_refusal_reason' => ['sf_consent_given', 'no'],
        'sf_consent_provider_role' => ['sf_consent_given', 'yes'],
        'sf_owner_disability_types' => ['owner_disability', 'yes'],
    ];

    private ?array $definitions = null;

    public function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }
        $survey = app(RehSurvey::class)->dictionary();
        $definitions = [];
        $assigned = [];
        foreach (self::GROUPS as $group => [$title, $scope, $sensitive, $fields]) {
            foreach ($fields as $field) {
                $assigned[$field] = [$group, $title, $sensitive];
            }
        }
        foreach ($survey['fields'] as $field => $source) {
            $scope = $source['scope'];
            $groupName = $source['group']['name'] ?? '';
            if (! isset(RehSurveyColumns::FIELDS[$scope][$field]) && $field !== 'location') {
                throw new \LogicException('Survey filter column is not mapped: '.$field);
            }
            [$column, $storageType] = RehSurveyColumns::FIELDS[$scope][$field] ?? ['geometry', 'geometry'];
            $sourceType = $source['type'];
            $choices = str_starts_with($sourceType, 'select_') ? ($survey['choices'][explode(' ', $sourceType, 2)[1]] ?? []) : [];
            $type = match (true) {
                in_array($sourceType, ['image', 'geoshape'], true) => 'presence',
                $storageType === 'reference' => 'text',
                in_array($sourceType, ['integer', 'decimal'], true) => $sourceType,
                $sourceType === 'date' => 'date',
                in_array($sourceType, ['dateTime', 'end'], true) => 'datetime',
                str_starts_with($sourceType, 'select_multiple') => 'multiple',
                $choices !== [] => 'choice',
                default => 'text',
            };
            if (isset($assigned[$field])) {
                [$group, $title, $sensitive] = $assigned[$field];
            } elseif ($scope === 'building') {
                [$group, $title, $sensitive] = ['building', self::GROUPS['building'][0], false];
            } elseif (str_starts_with($field, 'boq_')) {
                [$group, $title, $sensitive] = [$groupName, $source['group']['label'], false];
            } elseif (in_array($groupName, ['group_additional_work_items', 'group_photo_documentation'], true)) {
                [$group, $title, $sensitive] = [$groupName, $source['group']['label'], false];
            } elseif (str_starts_with($groupName, 'group_nonowner') || $groupName === 'group_hosted_person') {
                [$group, $title, $sensitive] = ['other', self::GROUPS['other'][0], true];
            } elseif (in_array($groupName, ['group_household_composition', 'group_social_vulnerability'], true) || $field === 'hosts_other_households') {
                [$group, $title, $sensitive] = ['family', self::GROUPS['family'][0], true];
            } elseif (in_array($groupName, ['group_unit_identification', 'group_unit_characteristics', 'group_unit_safety'], true)) {
                [$group, $title, $sensitive] = ['unit', self::GROUPS['unit'][0], false];
            } else {
                [$group, $title, $sensitive] = ['owner', self::GROUPS['owner'][0], true];
            }
            $label = RehSurvey::IDENTIFIER_LABELS[$field] ?? ['Field_status' => 'حالة الاستبيان في المصدر', 'collector_display_ip' => 'بيانات الباحث المسجلة', 'breadwinner_child_age' => 'عمر الابن أو الابنة المعيلين'][$field] ?? $source['label'];
            $definitions['sf_'.$field] = [
                'field' => $field, 'column' => $column, 'type' => $type,
                'label' => $label, 'group' => $group, 'title' => $title, 'scope' => $scope,
                'sensitive' => $sensitive, 'choices' => [...$choices, '__missing' => 'غير مسجل'],
                'operators' => RehFilterOperators::choices($type), 'default_operator' => RehFilterOperators::default($type),
                'context' => $source['group']['label'] ?? '',
            ];
        }

        return $this->definitions = $definitions;
    }

    public function groups(): array
    {
        $groups = [];
        foreach (self::GROUPS as $key => [$title, $scope, $sensitive]) {
            $groups[$key] = [$title, $scope, $sensitive];
        }
        foreach ($this->definitions() as $definition) {
            $groups[$definition['group']] = [$definition['title'], $definition['scope'], $definition['sensitive']];
        }

        return $groups;
    }

    public function keys(?string $scope = null, bool $sensitiveOnly = false): array
    {
        $keys = [];
        foreach ($this->definitions() as $key => $definition) {
            if (($scope !== null && $definition['scope'] !== $scope) || ($sensitiveOnly && ! $definition['sensitive'])) {
                continue;
            }
            $keys = [...$keys, $key, $key.'_op', ...(in_array($definition['type'], ['integer', 'decimal', 'date', 'datetime'], true) ? [$key.'_min', $key.'_max'] : [])];
        }

        return $scope === 'building' || $sensitiveOnly ? $keys : [...$keys, 'work_item', 'work_state', 'work_min', 'work_max', 'intervention_min', 'intervention_max', 'additional_work'];
    }

    public function workItems(): array
    {
        return array_filter(app(RehSurvey::class)->dictionary()['fields'], fn (array $definition, string $field): bool => str_starts_with($field, 'boq_'), ARRAY_FILTER_USE_BOTH);
    }

    public function rules(array $input): array
    {
        $rules = [];
        foreach ($this->definitions() as $key => $definition) {
            $type = $definition['type'];
            $operator = $input[$key.'_op'] ?? $definition['default_operator'];
            $presence = in_array($operator, ['blank', 'not_blank'], true);
            $rules[$key.'_op'] = ['nullable', 'string', Rule::in(array_keys($definition['operators']))];
            if (in_array($type, ['integer', 'decimal', 'date', 'datetime'], true)) {
                $valueRules = match ($type) {
                    'integer' => ['integer', 'between:-1000000,1000000'],
                    'decimal' => ['numeric', 'between:-1000000,1000000'],
                    'date' => ['date_format:Y-m-d'],
                    'datetime' => ['date_format:Y-m-d\\TH:i,Y-m-d\\TH:i:s'],
                };
                $rules[$key] = ['nullable', ...$valueRules, Rule::prohibitedIf($presence || $operator === 'between')];
                foreach (['min', 'max'] as $bound) {
                    $rules[$key.'_'.$bound] = ['nullable', ...$valueRules, Rule::prohibitedIf($presence || $operator !== 'between')];
                }
                if (isset($input[$key.'_min']) && $input[$key.'_min'] !== '') {
                    $rules[$key.'_max'][] = in_array($type, ['date', 'datetime'], true) ? 'after_or_equal:'.$key.'_min' : 'gte:'.$key.'_min';
                }
            } elseif (in_array($type, ['choice', 'multiple'], true)) {
                $rules = [...$rules, ...RehFilterChoices::rules($input, $key, array_keys($definition['choices']))];
                $rules[$key][] = Rule::prohibitedIf($presence);
            } else {
                $rules[$key] = ['nullable', 'string', 'max:1000', Rule::prohibitedIf($presence || $type === 'presence')];
            }
            if (! empty($input[$key.'_op']) && ! $presence) {
                $rules[$operator === 'between' ? $key.'_min' : $key][] = Rule::requiredIf($operator !== 'between' || ! isset($input[$key.'_max']) || $input[$key.'_max'] === '');
            }
        }
        foreach (self::DEPENDENCIES as $key => [$parent, $value]) {
            $operator = $input[$key.'_op'] ?? null;
            $parentOperator = $input[$parent.'_op'] ?? 'is';
            $allowed = in_array($operator, ['blank', 'not_blank'], true) || $parentOperator === 'is' && RehFilterChoices::contains($input[$parent] ?? null, $value);
            foreach ([$key, $key.'_op'] as $part) {
                $rules[$part][] = Rule::prohibitedIf(! $allowed);
            }
        }
        $rules = [...$rules, ...RehFilterChoices::rules($input, 'work_item', array_keys($this->workItems()))];
        $rules = [...$rules, ...RehFilterChoices::rules($input, 'work_state', ['positive', 'zero', 'missing'])];
        $rules['work_state'][] = 'required_with:work_item';
        foreach (['min', 'max'] as $bound) {
            $rules['work_'.$bound] = ['nullable', 'numeric', 'min:0', 'max:1000000', Rule::prohibitedIf(! RehFilterChoices::contains($input['work_state'] ?? null, 'positive'))];
            $rules['intervention_'.$bound] = ['nullable', 'numeric', 'min:0', 'max:1000000'];
        }
        if (isset($input['work_min']) && $input['work_min'] !== '') {
            $rules['work_max'][] = 'gte:work_min';
        }
        if (isset($input['intervention_min']) && $input['intervention_min'] !== '') {
            $rules['intervention_max'][] = 'gte:intervention_min';
        }
        $rules['work_item'][] = 'required_with:work_state,work_min,work_max';
        $rules = [...$rules, ...RehFilterChoices::rules($input, 'additional_work', ['yes', 'no'])];

        return $rules;
    }

    public function apply(Builder $query, array $filters, string $scope): Builder
    {
        foreach ($this->definitions() as $key => $definition) {
            if ($definition['scope'] !== $scope) {
                continue;
            }
            if (RehFilterOperators::active($filters, $key)) {
                app(RehFilterOperators::class)->apply($query, $query->qualifyColumn($definition['column']), $definition, $filters, $key);
            }
        }
        if ($scope === 'unit') {
            if (! empty($filters['work_item'])) {
                $query->where(function (Builder $q) use ($filters): void {
                    foreach (RehFilterChoices::values($filters['work_item']) as $item) {
                        foreach (RehFilterChoices::values($filters['work_state']) as $state) {
                            $q->orWhere(function (Builder $branch) use ($filters, $item, $state): void {
                                $column = $branch->qualifyColumn($item);
                                match ($state) {
                                    'positive' => $branch->where($column, '>', 0),
                                    'zero' => $branch->where($column, 0),
                                    'missing' => $branch->whereNull($column),
                                };
                                if ($state === 'positive') {
                                    $this->range($branch, $column, $filters, 'work');
                                }
                            });
                        }
                    }
                });
            }
            $this->range($query, $query->qualifyColumn('intervention_area_sqm'), $filters, 'intervention');
            if (count(RehFilterChoices::values($filters['additional_work'] ?? null)) === 1) {
                $query->where(function (Builder $q) use ($filters): void {
                    for ($index = 1; $index <= 3; $index++) {
                        $column = $q->qualifyColumn('additional_item_description_'.$index);
                        if (RehFilterChoices::contains($filters['additional_work'], 'yes')) {
                            $q->orWhere(fn (Builder $part) => $part->whereNotNull($column)->where($column, '!=', ''));
                        } else {
                            $q->where(fn (Builder $part) => $part->whereNull($column)->orWhere($column, ''));
                        }
                    }
                });
            }
        }

        return $query;
    }

    private function range(Builder $query, string $column, array $filters, string $key): void
    {
        foreach (['min' => '>=', 'max' => '<='] as $bound => $operator) {
            if (isset($filters[$key.'_'.$bound]) && $filters[$key.'_'.$bound] !== '') {
                $query->where($column, $operator, $filters[$key.'_'.$bound]);
            }
        }
    }

    public function labels(): array
    {
        $labels = ['work_item' => 'بند الأعمال', 'work_state' => 'حالة الكمية', 'work_min' => 'كمية الأعمال من', 'work_max' => 'كمية الأعمال إلى', 'intervention_min' => 'مساحة التدخل من', 'intervention_max' => 'مساحة التدخل إلى', 'additional_work' => 'بنود إضافية'];
        foreach ($this->definitions() as $key => $definition) {
            $label = $definition['title'].' · '.$definition['label'];
            $labels[$key] = $label;
            $labels[$key.'_op'] = $label.' · الشرط';
            if (in_array($definition['type'], ['integer', 'decimal', 'date', 'datetime'], true)) {
                $labels[$key.'_min'] = $label.' من';
                $labels[$key.'_max'] = $label.' إلى';
            } else {
                $labels[$key] = $label;
            }
        }

        return $labels;
    }

    public function summary(array $filters): array
    {
        $summary = [];
        foreach ($this->definitions() as $key => $definition) {
            if (! RehFilterOperators::active($filters, $key)) {
                continue;
            }
            $operator = $filters[$key.'_op'] ?? $definition['default_operator'];
            $values = in_array($operator, ['blank', 'not_blank'], true) ? [] : ($operator === 'between' ? [$filters[$key.'_min'] ?? '…', $filters[$key.'_max'] ?? '…'] : RehFilterChoices::values($filters[$key] ?? null));
            $answers = array_map(fn ($value): string => (string) ($definition['choices'][$value] ?? $value), array_values($values));
            $operatorLabel = $definition['operators'][$operator] ?? '';
            $answer = implode($operator === 'between' ? ' — ' : '، ', $answers);
            $summary[$definition['title'].' · '.$definition['label']] = in_array($operator, ['blank', 'not_blank'], true) ? $operatorLabel : ($operator === $definition['default_operator'] && ! isset($filters[$key.'_op']) && $operator !== 'between' ? '' : $operatorLabel.' · ').$answer;
        }
        foreach (['work_item', 'work_state', 'work_min', 'work_max', 'intervention_min', 'intervention_max', 'additional_work'] as $key) {
            $values = RehFilterChoices::values($filters[$key] ?? null);
            if ($values === []) {
                continue;
            }
            $summary[$this->labels()[$key]] = implode('، ', array_map(fn ($choice): string => match ($key) {
                'work_item' => $this->workItems()[$choice]['label'],
                'work_state' => ['positive' => 'أكبر من صفر', 'zero' => 'صفر مسجل', 'missing' => 'غير مسجل'][$choice],
                'additional_work' => $choice === 'yes' ? 'نعم' : 'لا',
                default => (string) $choice,
            }, $values));
        }

        return $summary;
    }
}
