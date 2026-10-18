<?php

namespace App\Modules\Reh\Services;

class RehSurvey
{
    public const WORKFLOW = ['new' => 'وارد جديد', 'in_review' => 'قيد التدقيق', 'returned' => 'معاد للاستكمال', 'second_assessment' => 'بانتظار تقييم ثانٍ', 'ready' => 'مكتمل التدقيق', 'decided' => 'تم توثيق القرار', 'source_changed' => 'تغيّرت بيانات المصدر'];

    public const ACTIONS = ['claim' => 'استلام الملف', 'engineering_approve' => 'اعتماد التدقيق الهندسي', 'social_approve' => 'اعتماد التدقيق الاجتماعي', 'return' => 'إرجاع للاستكمال', 'second_assessment' => 'طلب تقييم هندسي ثانٍ', 'select' => 'قبول مبدئي', 'exclude' => 'استبعاد', 'reopen' => 'إعادة فتح التدقيق', 'quantities' => 'مراجعة الكميات', 'source_changed' => 'تحديث البيانات الميدانية', 'source_missing' => 'السجل غير موجود في آخر مزامنة'];

    private ?array $dictionary = null;

    public function dictionary(): array
    {
        return $this->dictionary ??= json_decode(file_get_contents(dirname(__DIR__).'/resources/survey.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function label(string $field): string
    {
        return $this->dictionary()['fields'][$field]['label'] ?? $field;
    }

    public function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'غير مسجل';
        }
        $type = $this->dictionary()['fields'][$field]['type'] ?? '';
        if (str_starts_with($type, 'select_')) {
            $list = explode(' ', $type, 2)[1] ?? '';
            $choices = $this->dictionary()['choices'][$list] ?? [];
            if (str_starts_with($type, 'select_multiple')) {
                return implode('، ', array_map(fn (string $key): string => (string) ($choices[$key] ?? $key), preg_split('/[ ,]+/', (string) $value, flags: PREG_SPLIT_NO_EMPTY)));
            }

            return (string) ($choices[(string) $value] ?? $value);
        }
        if (in_array($type, ['date', 'dateTime', 'end'], true) && is_numeric($value)) {
            return \Carbon\CarbonImmutable::createFromTimestampMs((int) $value)->timezone(config('app.timezone'))->format($type === 'date' ? 'Y-m-d' : 'Y-m-d H:i');
        }

        return is_scalar($value) ? (string) $value : '—';
    }

    public function sections(array $source, string $scope, bool $sensitive): array
    {
        $sections = [];
        foreach ($this->dictionary()['fields'] as $field => $definition) {
            if ($definition['scope'] !== $scope || ! array_key_exists($field, $source) || in_array($definition['type'], ['image', 'hidden', 'calculate', 'geoshape'], true) || str_starts_with($field, 'boq_')) {
                continue;
            }
            $group = $definition['group']['name'] ?? 'general';
            $publicGroup = $scope === 'building' || in_array($group, ['group_unit_identification', 'group_unit_characteristics', 'group_unit_safety', 'group_additional_work_items', 'group_photo_documentation'], true);
            if (! $publicGroup && ! $sensitive) {
                continue;
            }
            $sections[$group]['label'] = $definition['group']['label'] ?? 'بيانات عامة';
            $sections[$group]['fields'][] = ['field' => $field, 'label' => $definition['label'], 'value' => $this->value($field, $source[$field])];
        }

        return $sections;
    }

    public function quantities(\App\Modules\Reh\Models\RehUnit $unit): array
    {
        $items = [];
        foreach ($this->dictionary()['fields'] as $field => $definition) {
            if (! str_starts_with($field, 'boq_')) {
                continue;
            }
            $source = $unit->source[$field] ?? null;
            $reviewed = $unit->quantity_overrides[$field] ?? null;
            $items[] = ['field' => $field, 'label' => $definition['label'], 'unit' => str_replace('الوحدة: ', '', $definition['hint'] ?? ''), 'type' => $definition['type'], 'source' => $source, 'reviewed' => $reviewed, 'quantity' => $reviewed ?? $source, 'group' => $definition['group']['label'] ?? ''];
        }

        return $items;
    }

    public function issues(\App\Modules\Reh\Models\RehUnit $unit): array
    {
        $issues = [];
        $source = $unit->source;
        $building = $unit->building->source;
        if ($unit->source_missing || $unit->building->source_missing) {
            $issues[] = 'السجل أو المبنى غير موجود في آخر مزامنة؛ يلزم التحقق من المصدر.';
        }
        foreach ([['consent_given', 'yes', 'الموافقة على المشاركة غير مثبتة.'], ['unit_safe_to_enter', 'yes', 'سلامة الوحدة للدخول غير مثبتة.'], ['unit_uxo_present', 'no', 'يلزم التحقق من مخلفات الحرب داخل الوحدة.'], ['unit_dangerous_debris_present', 'no', 'يلزم التحقق من الركام الخطر في الوحدة.'], ['demolished_units_above', 'no', 'يلزم التحقق من الوحدات المهدمة أعلى الوحدة.']] as [$field, $expected, $message]) {
            if (($source[$field] ?? null) !== $expected) {
                $issues[] = $message;
            }
        }
        foreach ([['building_structurally_safe', 'yes', 'سلامة المبنى غير مثبتة.'], ['building_uxo_present', 'no', 'يلزم التحقق من مخلفات الحرب في المبنى.'], ['building_dangerous_debris', 'no', 'يلزم التحقق من الوصول الآمن إلى المبنى.']] as [$field, $expected, $message]) {
            if (($building[$field] ?? null) !== $expected) {
                $issues[] = $message;
            }
        }
        if (($source['unit_structural_classification'] ?? null) === 'needs_second_eval') {
            $issues[] = 'التصنيف الميداني يتطلب تقييمًا هندسيًا ثانيًا.';
        }
        if (($source['unit_structural_classification'] ?? null) === 'final_exclusion') {
            $issues[] = 'التصنيف الميداني يشير إلى الاستبعاد النهائي.';
        }
        foreach (['prior_project_assistance' => ['yes', 'مسجلة استفادة سابقة.'], 'unit_needs_intervention' => ['no', 'مسجل أن الوحدة لا تحتاج إلى تدخل.'], 'current_unit_occupancy' => ['rented', 'الوحدة مسجلة كمؤجرة.']] as $field => [$value, $message]) {
            if (($source[$field] ?? null) === $value) {
                $issues[] = $message;
            }
        }
        if (empty($source['owner_full_name']) || empty($source['owner_identity_number'])) {
            $issues[] = 'بيانات تعريف المالك تحتاج إلى استكمال.';
        }
        if (($source['ownership_proof_status'] ?? null) !== 'yes') {
            $issues[] = 'إثبات الملكية يحتاج إلى تحقق.';
        }
        $ageFields = ['household_males_under5', 'household_males_age5_17', 'household_males_age18_60', 'household_males_over60', 'household_females_under5', 'household_females_age5_17', 'household_females_age18_60', 'household_females_over60'];
        $ages = array_intersect_key($source, array_flip($ageFields));
        if (is_numeric($source['household_size'] ?? null) && count(array_filter($ages, 'is_numeric')) === 8 && (float) array_sum($ages) !== (float) $source['household_size']) {
            $issues[] = 'مجموع الفئات العمرية لا يطابق عدد أفراد أسرة المالك.';
        }
        if ($unit->floor_number !== null && $unit->apartment_number !== null && $unit->building->units()->where('id', '!=', $unit->id)->where('floor_number', $unit->floor_number)->where('apartment_number', $unit->apartment_number)->where('source_missing', false)->exists()) {
            $issues[] = 'توجد وحدة أخرى بالطابق ورقم الشقة نفسيهما؛ راجع احتمال التكرار.';
        }

        return $issues;
    }
}
