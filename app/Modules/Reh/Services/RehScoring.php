<?php

namespace App\Modules\Reh\Services;

class RehScoring
{
    public const VERSION = 'phc-matrix-v3';

    public const STATUSES = ['calculated' => 'محسوب', 'missing' => 'بيانات ناقصة', 'invalid' => 'بيانات تحتاج تصحيحًا'];

    public const RULES = [
        'dependency' => 'نسبة الإعالة = مجموع الأفراد دون 18 وفوق 60 ÷ مجموع الأفراد من 18 إلى 60. مقام صفر يستلزم مراجعة البيانات.',
        'hosted' => 'تُقيّم الأسر المقيمة أو المستضافة كمجموعة من الأعداد المجمّعة، دون قسمة الأفراد أو مضاعفة النقاط بعدد الأسر. تُعتمد الأعلى بينها وبين أسرة المالك بعد اكتمالهما.',
        'fractional_walls' => 'الحوائط: صفر = صفر نقاط؛ أكبر من صفر وأقل من 5 = الشريحة الأولى؛ من 5 وأقل من 11 = الثانية؛ 11 فأكثر = الثالثة. دون تقريب.',
        'missing_values' => 'كل إجابة مؤثرة في السكور إلزامية عند انطباقها. الإجابة الناقصة لا تُعتبر صفرًا؛ تبقى الدرجة النهائية والأولوية معلقتين حتى الاستكمال.',
        'engineering_blanks' => 'ضمن حصر أعمال يحتوي كميات مدخلة، أو مع تسجيل أن الوحدة لا تحتاج تدخلًا، يُحتسب بند BoQ الخالي كعمل غير مدرج وصفر للاحتساب. غياب الحصر كله أو غياب حقل من بيانات المصدر يستلزم المراجعة.',
    ];

    public const PRIORITIES = ['very_high' => 'أولوية عالية جدًا', 'high' => 'أولوية عالية', 'medium' => 'أولوية متوسطة', 'low' => 'أولوية منخفضة'];

    private const ENGINEERING = [
        'kitchen' => ['المطبخ', 6, ['6.1', '6.2', '6.4', '6.5']],
        'bathroom' => ['الحمام', 6, ['9.2', '9.7', '9.8']],
        'entrance' => ['باب المدخل', 5, ['3.1', '3.7', '3.9']],
        'external_wall' => ['الحائط الخارجي', 4, ['2.1', '2.2', '2.5', '2.6', '2.7']],
        'internal_wall' => ['الحائط الداخلي', 4, ['2.3', '2.4']],
        'roof' => ['السقف', 3, ['8.1', '5.3', '5.5']],
        'openings' => ['الشبابيك والأبواب الأخرى', 3, ['3.2', '3.3', '3.4', '3.5', '3.6', '3.8', '3.10', '3.12', '4.1', '4.2', '4.3', '4.4', '4.5', '3.13']],
        'tank' => ['خزان المياه', 2, ['1.1', '12.1', '12.2']],
        'drainage' => ['مواسير التصريف', 2, ['11.3']],
        'sewer' => ['تنظيف الصرف الصحي', 2, ['1.2']],
        'paint' => ['الدهانات', 2, ['7.1']],
        'privacy_door' => ['باب ألمنيوم الحمام', 1, ['3.11']],
    ];

    public function __construct(private RehSurvey $survey) {}

    public function calculate(\App\Modules\Reh\Models\RehUnit $unit): array
    {
        $source = $unit->source ?? [];
        $owner = $this->social($source);
        $hasOtherFamily = ($source['hosts_other_households'] ?? null) !== 'no'
            || ($source['current_unit_occupancy'] ?? null) === 'displaced_family'
            || (float) ($source['hosted_individuals_count'] ?? 0) > 0
            || (float) ($source['displaced_individuals_count'] ?? 0) > 0;
        $other = $hasOtherFamily ? $this->social($source, true) : null;
        $engineering = $this->engineering($unit);
        $socialTotal = $owner['total'] !== null && ($other === null || $other['total'] !== null)
            ? max($owner['total'], $other['total'] ?? 0) : null;
        $selectedFamily = $socialTotal === null ? null : ($other !== null && $other['total'] > $owner['total'] ? 'other' : 'owner');
        $total = $socialTotal !== null && $engineering['total'] !== null ? $socialTotal + $engineering['total'] : null;

        return [
            'version' => self::VERSION, 'owner' => $owner, 'other' => $other,
            'engineering' => $engineering, 'social_total' => $socialTotal, 'selected_family' => $selectedFamily,
            'total' => $total, 'priority' => $total === null ? null : $this->priority($total),
            'status' => $total === null ? 'incomplete' : 'calculated', 'rules' => self::RULES,
            'source_changed' => $unit->workflow_status === 'source_changed' || $unit->source_missing,
        ];
    }

    public function snapshot(\App\Modules\Reh\Models\RehUnit $unit): array
    {
        $score = $this->calculate($unit);
        foreach (['owner', 'other', 'engineering'] as $part) {
            if ($score[$part] !== null) {
                $score[$part]['rows'] = array_map(fn (array $row): array => array_intersect_key($row, array_flip(['id', 'points', 'status'])), $score[$part]['rows']);
            }
        }
        unset($score['rules']);

        return $score;
    }

    public function engineering(\App\Modules\Reh\Models\RehUnit $unit): array
    {
        $catalog = [];
        $source = $unit->source ?? [];
        $quantities = $this->survey->quantities($unit);
        $hasAssessment = ($source['unit_needs_intervention'] ?? null) === 'no'
            || collect($quantities)->every(fn (array $item): bool => $this->number($item['quantity'], $item['type'] === 'integer')['status'] === 'calculated')
            || collect($quantities)->contains(function (array $item): bool {
                foreach (['source', 'quantity'] as $key) {
                    $number = $this->number($item[$key], $item['type'] === 'integer');
                    if ($number['status'] === 'calculated' && $number['value'] > 0) {
                        return true;
                    }
                }

                return false;
            });
        foreach ($quantities as $item) {
            $item['not_requested'] = $hasAssessment
                && array_key_exists($item['field'], $source)
                && ($item['quantity'] === null || $item['quantity'] === '');
            $item['scoring_quantity'] = $item['not_requested'] ? 0 : $item['quantity'];
            if (preg_match('/^boq_(\d+)_(\d+)_/', $item['field'], $match)) {
                $catalog[$match[1].'.'.$match[2]] = $item;
            }
        }
        $rows = [];
        foreach (self::ENGINEERING as $id => [$label, $maximum, $codes]) {
            $items = array_map(fn (string $code): array => ['code' => $code] + $catalog[$code], $codes);
            $numbers = array_map(fn (array $item): array => $this->number($item['scoring_quantity'], $item['type'] === 'integer'), $items);
            $state = $this->inputState($numbers);
            $points = null;
            $reason = 'تُحتسب نقاط الفئة مرة واحدة عند وجود كمية موجبة في أحد بنودها.';
            $wall = in_array($id, ['internal_wall', 'external_wall'], true);
            if ($state !== 'invalid' && ! $wall && collect($numbers)->contains(fn (array $number): bool => $number['status'] === 'calculated' && $number['value'] > 0)) {
                $state = 'calculated';
                $points = $maximum;
            } elseif ($state === 'calculated') {
                if ($wall) {
                    $values = array_column($numbers, 'value');
                    $points = $id === 'internal_wall'
                        ? $this->wallPoints(array_sum($values), false)
                        : max($this->wallPoints($values[0] + $values[1], true), $this->wallPoints($values[2] + $values[3] + $values[4], true));
                    $reason = ($id === 'internal_wall'
                        ? 'مجموع 2.3 + 2.4: الشرائح تمنح 1 ثم 2 ثم 4 نقاط. '
                        : 'تُقيّم مجموعة 2.1 + 2.2 ومجموعة 2.5 + 2.6 + 2.7 كل على حدة، ثم تؤخذ أعلى النقاط: 1 ثم 3 ثم 4. ')
                        .self::RULES['fractional_walls'];
                } else {
                    $points = 0;
                }
            }
            if (in_array($state, ['missing', 'invalid'], true)) {
                $reason = $state === 'missing' ? 'حصر الأعمال غير متوفر أو أن حقولًا مطلوبة لم تصل من المصدر؛ يلزم مراجعة البيانات.' : 'توجد كمية سالبة أو غير صالحة أو كسر في بند عددي.';
            } elseif ($points === 0) {
                $reason = 'لا توجد كمية موجبة مدرجة لهذه الفئة ضمن حصر الأعمال؛ صفر نقاط. '.($wall ? self::RULES['fractional_walls'] : 'تُمنح نقاط الفئة عند وجود كمية موجبة في أحد بنودها.');
            }
            $rows[] = $this->row($id, $label, $maximum, $points, $state, $reason, $items);
        }

        return $this->summary($rows, 40);
    }

    private function wallPoints(float $value, bool $external): int
    {
        return match (true) {
            $value === 0.0 => 0, $value < 5 => 1, $value < 11 => $external ? 3 : 2, default => 4,
        };
    }

    public function priority(int $total): string
    {
        return match (true) {
            $total >= 76 => 'very_high', $total >= 55 => 'high', $total >= 30 => 'medium', default => 'low',
        };
    }

    private function social(array $source, bool $other = false): array
    {
        $prefix = $other ? 'nonowner_' : 'household_';
        $headPrefix = $other ? (($source['current_unit_occupancy'] ?? null) === 'displaced_family' ? 'resident_' : 'hosted_') : 'owner_';
        $ageField = $headPrefix.'age';
        $sexField = $headPrefix.'sex';
        $rows = [];
        $rows[] = $this->numeric($source, 'age', $other ? 'سن ممثل الأسر' : 'سن رب الأسرة', 3, [$ageField], fn (float $n): int => $n > 60 ? 3 : 0, 'أكبر من 60 سنة = 3؛ وإلا 0.');
        $elderFields = [$prefix.'males_over60', $prefix.'females_over60'];
        $elder = $this->numeric($source, 'elders', 'كبار السن الآخرون', 3, [...$elderFields, $ageField], function (float $ignored, array $values): int {
            return $this->countPoints($values[0] + $values[1] - ($values[2] > 60 ? 1 : 0));
        }, 'باستثناء المالك أو ممثل الأسر: لا أحد = 0؛ فرد = 2؛ فردان فأكثر = 3.');
        if ($elder['status'] === 'calculated' && (float) ($source[$ageField] ?? 0) > 60 && array_sum(array_map(fn (string $field): float => (float) $source[$field], $elderFields)) < 1) {
            $elder = $this->row('elders', 'كبار السن الآخرون', 3, null, 'invalid', 'العمر يتجاوز 60 لكن عدد كبار السن المسجل لا يشمله.', $elder['inputs']);
        }
        $rows[] = $elder;
        foreach (['vision' => ['بصرية', 'other_vision_difficulty_count'], 'hearing' => ['سمعية', 'other_hearing_difficulty_count'], 'walking' => ['حركية', 'other_walking_difficulty_count'], 'memory' => ['إدراكية', 'other_memory_difficulty_count'], 'selfcare' => ['عناية ذاتية', 'other_selfcare_diff_count'], 'communication' => ['تواصل', 'other_communication_diff_count']] as $key => [$label, $field]) {
            $row = $this->numeric($source, 'disability_'.$key, ($other ? 'الإعاقة لدى المجموعة · ' : 'إعاقة بقية الأسرة · ').$label, 2, [$other ? 'nonowner_'.$key.'_count' : $field], fn (float $n): int => (int) min(2, $n), ($other ? 'عدد الأفراد المسجل ضمن المجموعة المجمّعة' : 'باستثناء المالك').': لا أحد = 0؛ فرد = 1؛ فردان فأكثر = 2.');
            $disabilityGate = $other ? 'nonowner_has_disability' : 'other_members_have_disability';
            if (($source[$disabilityGate] ?? null) === 'no') {
                if ($row['status'] === 'missing' || $row['points'] === 0) {
                    $row = $this->choice($source, $row['id'], $row['label'], 2, $disabilityGate, ['no' => 0], 'مسجل صراحة عدم وجود إعاقة لأفراد المجموعة المقصودة.');
                } else {
                    $row = $this->row($row['id'], $row['label'], 2, null, 'invalid', 'عدد الإعاقات يتعارض مع الإجابة بعدم وجود إعاقة.', $row['inputs']);
                }
            }
            $rows[] = $row;
        }
        $rows[] = ! $other
            ? $this->choice($source, 'head_disability', 'إعاقة المالك', 3, 'owner_has_disability', ['yes' => 3, 'no' => 0], 'نعم = 3؛ لا = 0.')
            : (($source['nonowner_has_disability'] ?? null) === 'no'
                ? $this->choice($source, 'head_disability', 'إعاقة ممثل الأسر', 3, 'nonowner_has_disability', ['no' => 0], 'مسجل عدم وجود إعاقة بين أفراد المجموعة، بما فيهم ممثلها.')
                : $this->row('head_disability', 'إعاقة ممثل الأسر', 3, null, 'missing', 'البيانات المجمّعة لا تحدد إن كانت الإعاقة تخص ممثل الأسر. يلزم استكمال هذه المعلومة وفصلها عن أعداد بقية الأفراد قبل اعتماد المقارنة.', $this->inputs($source, ['nonowner_has_disability', 'nonowner_disability_count'])));
        foreach (['nfi' => ['مساعدة المواد غير الغذائية', 'nonfood_assistance_recency'], 'shelter' => ['مساعدة المأوى', 'shelter_assistance_recency']] as $id => [$label, $field]) {
            $rows[] = $this->choice($source, $id, $label, 1, $other ? 'nonowner_'.$id.'_recency' : $field, ['recent' => 0, 'm6_12' => 1, 'm12_plus' => 1], 'خلال آخر 6 أشهر = 0؛ قبل ذلك = 1.');
        }
        $rows[] = $this->choice($source, 'female', $other ? 'ممثلة الأسر أنثى' : 'ربة أسرة', 3, $sexField, ['female' => 3, 'male' => 0], 'أنثى = 3؛ ذكر = 0.');
        foreach ([['pregnancy', 'الحوامل والمرضعات', [$prefix.'pregnant_lactating']], ['unaccompanied', 'الأطفال غير المصحوبين', [$other ? 'nonowner_unaccompanied_count' : 'unaccompanied_child_count']], ['chronic', 'المصابون بأمراض مزمنة', [$prefix.'chronic_count']]] as [$id, $label, $fields]) {
            $row = $this->numeric($source, $id, $label, 3, $fields, fn (float $n): int => $this->countPoints($n), 'لا أحد = 0؛ فرد = 2؛ اثنان فأكثر = 3.');
            if ($other && $id === 'chronic' && ($source['nonowner_has_chronic'] ?? null) === 'no') {
                $row = $row['status'] === 'missing' || $row['points'] === 0
                    ? $this->choice($source, $id, $label, 3, 'nonowner_has_chronic', ['no' => 0], 'مسجل صراحة عدم وجود أمراض مزمنة في المجموعة.')
                    : $this->row($id, $label, 3, null, 'invalid', 'عدد المصابين يتعارض مع الإجابة بعدم وجود أمراض مزمنة.', $row['inputs']);
            }
            $rows[] = $row;
        }
        $rows[] = $this->numeric($source, 'under5', 'الأطفال دون خمس سنوات', 3, [$prefix.'males_under5', $prefix.'females_under5'], fn (float $n): int => $n === 0.0 ? 0 : ($n <= 3 ? 2 : 3), 'لا أحد = 0؛ 1–3 = 2؛ أربعة فأكثر = 3.');
        $rows[] = $this->numeric($source, 'minor', $other ? 'ممثل الأسر قاصر' : 'رب أسرة قاصر', 3, [$ageField], fn (float $n): int => $n < 18 ? 3 : 0, 'العمر أقل من 18 = 3؛ وإلا 0.');
        $rows[] = $this->choice($source, 'income', 'دخل غير ثابت', 3, $other ? 'nonowner_has_stable_income' : 'household_has_stable_income', ['no' => 3, 'yes' => 0], 'لا يوجد دخل ثابت = 3؛ يوجد = 0.');
        $field = $other ? 'nonowner_protection_risks' : 'household_protection_risks';
        $risks = $source[$field] ?? null;
        $tokens = is_string($risks) ? array_values(array_unique(preg_split('/[ ,]+/', trim($risks), flags: PREG_SPLIT_NO_EMPTY))) : [];
        $allowed = $this->survey->dictionary()['choices'][$other ? 'nonowner_protection_risks' : 'protection_risks'];
        $state = $risks === null || $risks === '' ? 'missing' : (empty($tokens) || array_diff($tokens, array_keys($allowed)) || (in_array('none', $tokens, true) && count($tokens) > 1) ? 'invalid' : 'calculated');
        $rows[] = $this->row('protection', 'مخاطر الحماية', 3, $state === 'calculated' ? $this->countPoints(in_array('none', $tokens, true) ? 0 : count($tokens)) : null, $state, 'لا مخاطر = 0؛ خطر واحد = 2؛ خطران فأكثر = 3. لا تُحسب الاختيارات المكررة مرتين.', $this->inputs($source, [$field]));
        $rows[] = $this->dependency($source, $prefix);
        $sex = $source[$sexField] ?? null;
        $breadwinnerField = $other ? 'nonowner_breadwinner_'.($sex === 'male' ? 'm' : 'f') : ($sex === 'male' ? 'breadwinner_male_owner' : 'breadwinner_female_owner');
        $rows[] = $this->choice($source, 'breadwinner', 'معيل الأسرة', 3, $breadwinnerField, $sex === 'male' ? ['owner' => 0, 'wife' => 3, 'minor_child' => 3, 'adult_son' => 2, 'adult_daughter' => 2, 'relative' => 0, 'other' => 0] : ($sex === 'female' ? ['owner' => 0, 'husband' => 0, 'minor_child' => 3, 'adult_son' => 2, 'adult_daughter' => 2, 'relative' => 0, 'other' => 0] : []), 'اختيار ابن قاصر = 3؛ ابن/ابنة بالغ = 2؛ الزوجة = 3؛ غير ذلك = 0.');
        $hosts = $source['hosts_other_households'] ?? null;
        $displaced = ($source['current_unit_occupancy'] ?? null) === 'displaced_family';
        $individualsField = $displaced ? 'displaced_individuals_count' : 'hosted_individuals_count';
        $hosted = $hosts === 'no' && ! $displaced ? $this->choice($source, 'hosting', 'الأفراد المستضافون', 3, 'hosts_other_households', ['no' => 0], 'مسجل عدم وجود أسرة مستضافة.') : $this->numeric($source, 'hosting', 'الأفراد المستضافون', 3, [$individualsField], fn (float $n): int => $n === 0.0 ? 0 : ($n <= 3 ? 2 : 3), 'يُحسب مجموع الأفراد دون تقسيمه أو مضاعفته بعدد الأسر: 1–3 = 2؛ أربعة فأكثر = 3.');
        if (! in_array($hosts, ['yes', 'no'], true) && ! $displaced) {
            $hosted = $this->row('hosting', 'الأفراد المستضافون', 3, null, $hosts === null || $hosts === '' ? 'missing' : 'invalid', 'يلزم تحديد وجود أسر أخرى في الوحدة.', $this->inputs($source, ['hosts_other_households']));
        } elseif ((! $displaced && $hosts === 'no' && (float) ($source[$individualsField] ?? 0) > 0) || (($hosts === 'yes' || $displaced) && $hosted['status'] === 'calculated' && $hosted['points'] === 0)) {
            $hosted = $this->row('hosting', 'الأفراد المستضافون', 3, null, 'invalid', 'إجابة الاستضافة لا تتفق مع عدد الأفراد.', $hosted['inputs']);
        }
        $rows[] = $hosted;
        $occupied = in_array($source['current_unit_occupancy'] ?? null, ['owner_family', 'displaced_family', 'rented'], true);
        $rows[] = $occupied ? $this->row('vacancy', 'سبب خلو الوحدة', 3, 0, 'calculated', 'الوحدة مأهولة؛ مؤشر سبب الخلو لا ينطبق.', $this->inputs($source, ['current_unit_occupancy'])) : $this->choice($source, 'vacancy', 'سبب خلو الوحدة', 3, 'vacancy_reason', ($source['current_unit_occupancy'] ?? null) === 'empty' ? ['displaced_tent' => 3, 'cant_afford' => 2, 'displaced_alt' => 1, 'under_repair_sale' => 0, 'ownership_dispute' => 0, 'other_vacancy' => 0] : [], 'خيمة = 3؛ عجز مالي = 2؛ سكن بديل = 1؛ غير ذلك = 0.');

        return $this->summary($rows, 60);
    }

    private function numeric(array $source, string $id, string $label, int $maximum, array $fields, callable $calculate, string $reason): array
    {
        $numbers = array_map(fn (string $field): array => $this->number($source[$field] ?? null, true, in_array($field, ['owner_age', 'resident_age', 'hosted_age'], true) ? 120 : 1000000), $fields);
        $state = $this->inputState($numbers);
        $values = array_column($numbers, 'value');

        return $this->row($id, $label, $maximum, $state === 'calculated' ? $calculate(array_sum($values), $values) : null, $state, $reason, $this->inputs($source, $fields));
    }

    private function choice(array $source, string $id, string $label, int $maximum, string $field, array $map, string $reason): array
    {
        $value = $source[$field] ?? null;
        $state = $value === null || $value === '' ? 'missing' : (is_string($value) && array_key_exists($value, $map) ? 'calculated' : 'invalid');

        return $this->row($id, $label, $maximum, $state === 'calculated' ? $map[$value] : null, $state, $reason, $this->inputs($source, [$field]));
    }

    private function countPoints(float $count): int
    {
        return $count <= 0 ? 0 : ($count === 1.0 ? 2 : 3);
    }

    private function number(mixed $value, bool $integer = false, int $maximum = 1000000): array
    {
        if ($value === null || $value === '') {
            return ['status' => 'missing', 'value' => null];
        }
        if (! is_numeric($value) || is_bool($value) || ! is_finite((float) $value) || (float) $value < 0 || (float) $value > $maximum || ($integer && floor((float) $value) !== (float) $value)) {
            return ['status' => 'invalid', 'value' => null];
        }

        return ['status' => 'calculated', 'value' => (float) $value];
    }

    private function inputState(array $numbers): string
    {
        $states = array_column($numbers, 'status');

        return in_array('invalid', $states, true) ? 'invalid' : (in_array('missing', $states, true) ? 'missing' : 'calculated');
    }

    private function inputs(array $source, array $fields): array
    {
        return array_map(fn (string $field): array => ['field' => $field, 'label' => $this->survey->label($field), 'display' => $this->survey->value($field, is_scalar($source[$field] ?? null) ? $source[$field] : null)], $fields);
    }

    private function dependency(array $source, string $prefix): array
    {
        $fields = array_map(fn (string $suffix): string => $prefix.$suffix, ['males_under5', 'males_age5_17', 'males_over60', 'females_under5', 'females_age5_17', 'females_over60', 'males_age18_60', 'females_age18_60']);
        $numbers = array_map(fn (string $field): array => $this->number($source[$field] ?? null, true), $fields);
        $state = $this->inputState($numbers);
        $points = null;
        $reason = self::RULES['dependency'];
        if ($state === 'calculated') {
            $values = array_column($numbers, 'value');
            $dependents = array_sum(array_slice($values, 0, 6));
            $workingAge = $values[6] + $values[7];
            if ($workingAge === 0.0) {
                $state = 'invalid';
                $reason = 'عدد الأفراد من 18 إلى 60 صفر؛ يلزم مراجعة تركيبة الأسرة، ولا تُمنح نقاط تلقائية لهذه الحالة.';
            } else {
                $ratio = $dependents / $workingAge;
                $points = $ratio <= 1 ? 0 : ($ratio <= 2 ? 2 : 4);
                $reason .= ' '.$dependents.' ÷ '.$workingAge.' = '.round($ratio, 3).'؛ حتى 1 = 0؛ أكبر من 1 حتى 2 = 2؛ أكبر من 2 = 4.';
            }
        }

        return $this->row('dependency', 'نسبة الإعالة', 4, $points, $state, $reason, $this->inputs($source, $fields));
    }

    private function row(string $id, string $label, int $maximum, ?int $points, string $status, string $reason, array $inputs): array
    {
        return compact('id', 'label', 'maximum', 'points', 'status', 'reason', 'inputs');
    }

    private function summary(array $rows, int $maximum): array
    {
        $calculated = array_filter($rows, fn (array $row): bool => $row['status'] === 'calculated');
        $subtotal = (int) array_sum(array_column($calculated, 'points'));

        return ['rows' => $rows, 'subtotal' => $subtotal, 'maximum' => $maximum, 'calculated_count' => count($calculated), 'pending_count' => count($rows) - count($calculated), 'total' => count($calculated) === count($rows) ? $subtotal : null];
    }
}
