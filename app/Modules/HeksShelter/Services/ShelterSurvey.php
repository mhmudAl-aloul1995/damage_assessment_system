<?php

namespace App\Modules\HeksShelter\Services;

use App\Modules\HeksShelter\Http\Requests\ShelterSurveyRequest;
use App\services\MobileFormDefinitionService;
use Illuminate\Validation\Validator;

class ShelterSurvey
{
    private ?array $cachedSections = null;

    private ?array $cachedFields = null;

    public const STATUSES = ['received' => 'وارد', 'review' => 'قيد التدقيق', 'returned' => 'بحاجة لاستكمال', 'blocked' => 'موقوف للأهلية', 'approved' => 'معتمد', 'in_progress' => 'قيد التنفيذ', 'completed' => 'مكتمل', 'excluded' => 'مستبعد'];

    public function sections(): array
    {
        return $this->cachedSections ??= require dirname(__DIR__).'/resources/survey.php';
    }

    public function fields(): array
    {
        return $this->cachedFields ??= collect($this->sections())->flatMap(fn (array $section): array => $section['fields'])->keyBy('key')->all();
    }

    public function definition(MobileFormDefinitionService $builder): array
    {
        $sections = array_map(fn (array $section): array => [
            'title' => $section['title'],
            'fields' => array_map(fn (array $field): array => $builder->field($field['key'], $field['label'], $field['type'], $field['choices'], $field['required'], visible: $field['visible'], exclusive: ['none']), $section['fields']),
        ], $this->sections());

        return $builder->define('heks-shelter', 'HEKS — استبيان المرحلة الثالثة لإصلاح المأوى', false, (new ShelterSurveyRequest)->rules(), $sections);
    }

    public function value(string $key, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return 'غير مسجل';
        }
        $choices = $this->fields()[$key]['choices'] ?? [];
        if (is_array($value)) {
            return implode('، ', array_map(fn (string $item): string => $choices[$item] ?? $item, $value));
        }

        return $choices[(string) $value] ?? (string) $value;
    }

    public static function normalizeIdentity(string $identity): string
    {
        return trim(strtr($identity, array_combine(preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789'))));
    }

    public function validateConsistency(Validator $validator, array $answers): void
    {
        if (($answers['eligibility'] ?? null) === 'blocked' && empty($answers['safety_risks'])) {
            $validator->errors()->add('safety_risks', 'حدد سبب إيقاف التقييم.');
        }
        foreach (['family', 'disability', 'chronic', 'injured', 'extended'] as $prefix) {
            $keys = [];
            foreach (['under8', '8_17', '18_59', '60_plus'] as $age) {
                foreach (['male', 'female'] as $gender) {
                    $keys[] = $prefix.'_'.$age.'_'.$gender;
                }
            }
            $values = array_intersect_key($answers, array_flip($keys));
            if (count(array_filter($values, fn (mixed $value): bool => $value !== null && $value !== '')) === 8 && isset($answers[$prefix.'_total']) && array_sum($values) !== (int) $answers[$prefix.'_total']) {
                $validator->errors()->add($prefix.'_total', 'الإجمالي لا يطابق توزيع الأعمار والجنس.');
            }
            if (in_array($prefix, ['disability', 'chronic', 'injured'], true) && isset($answers[$prefix.'_total'], $answers['family_total']) && $answers[$prefix.'_total'] > $answers['family_total']) {
                $validator->errors()->add($prefix.'_total', 'العدد لا يمكن أن يتجاوز إجمالي أفراد الأسرة.');
            }
        }
    }

    public function blockers(array $answers): array
    {
        $reasons = [];
        if (($answers['eligibility'] ?? null) !== 'safe' || ! empty($answers['safety_risks'])) {
            $reasons[] = 'لم تثبت أهلية الوحدة وسلامتها لاستكمال التدخل.';
        }
        foreach (['safe_environment', 'safe_entry', 'structurally_safe', 'ownership_proof'] as $key) {
            if (($answers[$key] ?? null) !== 'yes') {
                $reasons[] = $this->fields()[$key]['label'].': بحاجة للتحقق والاستكمال.';
            }
        }
        foreach (['uxo', 'legal_dispute', 'owner_permission_obstacle'] as $key) {
            if (($answers[$key] ?? null) !== 'no') {
                $reasons[] = $this->fields()[$key]['label'].': لم تتم معالجة المانع أو التحقق منه.';
            }
        }
        if (in_array('study', $answers['recommendations'] ?? [], true)) {
            $reasons[] = 'التوصية الحالية تتطلب دراسة فنية قبل التدخل.';
        }
        foreach (['family_total', 'unit_area', 'damage', 'address'] as $key) {
            if (! isset($answers[$key]) || $answers[$key] === '' || in_array($key, ['family_total', 'unit_area'], true) && (float) $answers[$key] <= 0) {
                $reasons[] = 'استكمل '.$this->fields()[$key]['label'].'.';
            }
        }
        if (($answers['can_manage_cash'] ?? null) !== 'yes' && ! in_array('guarantor', $answers['recommendations'] ?? [], true)) {
            $reasons[] = 'تحقق من قدرة الأسرة على إدارة المنحة أو وثّق توصية توفير كفيل.';
        }

        return $reasons;
    }
}
