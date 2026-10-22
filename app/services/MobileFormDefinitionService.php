<?php

namespace App\services;

use App\Modules\DamageAssessmentBorrowers\Http\Requests\StoreBorrowerSurveyRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksBeneficiaryRequest;
use App\Modules\Heks\Http\Requests\UpdateHeksFollowUpRequest;

class MobileFormDefinitionService
{
    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        $borrower = new StoreBorrowerSurveyRequest;
        $heks = new UpdateHeksBeneficiaryRequest;
        $followup = new UpdateHeksFollowUpRequest;

        return [
            'heks-phase-three' => app(\App\Modules\HeksShelter\Services\ShelterSurvey::class)->definition($this),
            'borrower-survey' => $this->define('damage_assessment_borrowers', 'استبيان المقترض والأسرة والوحدة السكنية', false, $borrower->rules(), [
                ['title' => 'المقترض والأسرة', 'fields' => [
                    $this->field('borrower_name', 'اسم المقترض', required: true),
                    $this->field('borrower_id_number', 'رقم الهوية'),
                    $this->field('form_number', 'رقم الاستمارة'),
                    $this->field('surveyed_at', 'تاريخ ووقت الزيارة', 'datetime'),
                    $this->field('family_members_count', 'عدد أفراد الأسرة', 'integer'),
                    $this->field('marital_status', 'الحالة الاجتماعية', 'select', ['married' => 'متزوج/ة', 'single' => 'أعزب/عزباء', 'widowed' => 'أرمل/ة', 'divorced' => 'مطلق/ة', 'abandoned' => 'مهجور/ة']),
                    $this->field('is_borrower_alive', 'هل المقترض على قيد الحياة؟', 'boolean', required: true),
                    $this->field('spouse_name', 'اسم الزوج/ة'),
                    $this->field('spouse_id_number', 'هوية الزوج/ة'),
                    $this->field('employment_status', 'الوضع الوظيفي', 'select', $this->employment()),
                    $this->field('vulnerability_types', 'مؤشرات الهشاشة', 'multiselect', ['martyrs' => 'يوجد شهداء', 'injured' => 'يوجد مصابون', 'disabled' => 'أشخاص ذوو إعاقة', 'elderly' => 'كبار سن', 'none' => 'لا يوجد'], exclusive: ['none']),
                ]],
                ['title' => 'الكفلاء', 'fields' => [
                    $this->field('guarantors_count', 'عدد الكفلاء', 'integer'),
                    $this->field('guarantors_alive_status', 'هل جميع الكفلاء على قيد الحياة؟', 'select', ['yes' => 'نعم', 'no' => 'لا', 'none' => 'لا يوجد']),
                    $this->field('guarantors_employment_statuses', 'الوضع الوظيفي للكفلاء', 'multiselect', ['all_working' => 'جميعهم يعملون', 'retired' => 'يوجد متقاعد', 'lost_job' => 'يوجد فاقد عمل']),
                    $this->field('deceased_guarantors', 'الكفلاء المتوفون', 'repeat', children: [$this->field('name', 'اسم الكفيل')]),
                    $this->field('affected_guarantors', 'الكفلاء المتأثرون', 'repeat', children: [$this->field('name', 'اسم الكفيل'), $this->field('status', 'الحالة', 'select', ['retired' => 'متقاعد', 'lost_job' => 'فقد العمل'])]),
                ]],
                ['title' => 'النزوح والتواصل', 'fields' => [
                    $this->field('displacement_status', 'حالة النزوح', 'select', ['displaced' => 'نازح', 'returned' => 'عائد', 'resident' => 'مقيم']),
                    $this->field('displaced_to_governorate', 'المحافظة النازح إليها', 'select', ['north' => 'الشمال', 'gaza' => 'غزة', 'middle' => 'الوسطى', 'khan_younis' => 'خانيونس', 'rafah' => 'رفح'], visible: ['field' => 'displacement_status', 'equals' => 'displaced']),
                    $this->field('phone_primary', 'رقم التواصل الأول', 'phone'),
                    $this->field('phone_secondary', 'رقم التواصل الثاني', 'phone'),
                    $this->field('current_residence_address', 'عنوان السكن الحالي', 'multiline'),
                ]],
                ['title' => 'الوحدة السكنية', 'fields' => [
                    $this->field('loan_unit_address', 'عنوان الوحدة', 'multiline'),
                    $this->field('loan_unit_area', 'المساحة م²', 'decimal'),
                    $this->field('loan_unit_floor_type', 'الطابق', 'select', ['ground' => 'أرضي', 'repeated' => 'متكرر']),
                    $this->field('parcel_number', 'رقم القطعة'),
                    $this->field('plot_number', 'رقم القسيمة'),
                    $this->field('loan_unit_occupancy_status', 'الإشغال', 'select', ['owner_borrower' => 'المقترض', 'tenants' => 'مستأجرون', 'displaced_hosted' => 'نازحون أو مستضافون', 'buyers' => 'مشترون', 'heirs' => 'ورثة', 'none_due_damage' => 'لا يوجد بسبب الضرر']),
                    $this->field('loan_unit_damage_status', 'حالة الضرر', 'select', ['destroyed' => 'هدم كلي', 'severe_uninhabitable' => 'بليغ غير صالح للسكن', 'severe_habitable' => 'بليغ صالح للسكن', 'minor' => 'أضرار طفيفة']),
                    $this->field('resident_households', 'الأسر المقيمة في الوحدة', 'repeat', children: [
                        $this->field('head_name', 'اسم رب الأسرة'), $this->field('id_number', 'الهوية'),
                        $this->field('members_count', 'عدد الأفراد', 'integer'), $this->field('phone', 'الهاتف', 'phone'),
                        $this->field('employment_status', 'الوضع الوظيفي', 'select', $this->employment()),
                    ]),
                    $this->field('notes', 'ملاحظات', 'multiline'),
                ]],
            ]),
            'heks-beneficiary' => $this->define('heks', 'تحديث بيانات مستفيد HEKS', true, $heks->rules(), [
                ['title' => 'المستفيد والزيارة', 'fields' => [
                    $this->field('name', 'اسم المستفيد'), $this->field('identity_number', 'الهوية'),
                    $this->field('phone', 'الهاتف', 'phone'), $this->field('alternate_phone', 'هاتف بديل', 'phone'),
                    $this->field('field_engineer', 'المهندس الميداني'), $this->field('visit_date', 'تاريخ الزيارة', 'date'),
                    $this->field('governorate', 'المحافظة'), $this->field('area', 'المنطقة'), $this->field('address', 'العنوان', 'multiline'),
                    $this->field('displacement_status', 'النزوح'), $this->field('occupancy_status', 'الإشغال'), $this->field('damage_status', 'حالة الضرر'),
                ]],
                ['title' => 'الاختيار والدفعات', 'fields' => [
                    $this->field('is_selected', 'مستفيد مختار', 'boolean'), $this->field('selection_status', 'حالة الاختيار'),
                    $this->field('payment_status', 'حالة الدفعات'), $this->field('grant_amount', 'مبلغ المنحة', 'decimal'),
                    $this->field('payment_1', 'الدفعة الأولى', 'decimal'), $this->field('payment_2', 'الدفعة الثانية', 'decimal'), $this->field('payment_3', 'الدفعة الثالثة', 'decimal'),
                ]],
                ['title' => 'الملاحظات والتوصيات', 'fields' => [
                    $this->field('social_notes', 'ملاحظات اجتماعية', 'multiline'), $this->field('engineer_notes', 'ملاحظات هندسية', 'multiline'), $this->field('recommendations', 'التوصيات', 'multiline'),
                ]],
            ]),
            'heks-followup' => $this->define('heks', 'تحديث زيارة متابعة HEKS', true, array_diff_key($followup->rules(), array_flip(['boq_filename', 'boq_url'])), [
                ['title' => 'الزيارة والأعمال', 'fields' => [
                    $this->field('visit_number', 'رقم الزيارة'), $this->field('visit_date', 'تاريخ الزيارة', 'date'),
                    $this->field('engineer_name', 'اسم المهندس'), $this->field('working_condition', 'حالة الأعمال', 'select', [
                        'the_work_have_not_been_started' => 'لم تبدأ الأعمال', 'work_in_progress__but_not_due_for_second' => 'جارٍ العمل، لم تستحق الدفعة الثانية',
                        'work_in_progress__and_due_for_next_payme' => 'جارٍ العمل، تستحق الدفعة التالية', 'work_has_been_finished_and_due_for_the_f' => 'انتهت الأعمال وتستحق الدفعة الأخيرة', 'other_condition' => 'حالة أخرى',
                    ]),
                    $this->field('other_condition', 'وصف الحالة الأخرى', 'multiline', visible: ['field' => 'working_condition', 'equals' => 'other_condition']),
                    $this->field('completed_amount_ils', 'قيمة الأعمال المنجزة بالشيكل', 'decimal'),
                    $this->field('completion_percentage', 'نسبة الإنجاز %', 'decimal'),
                    $this->field('engineer_recommendations', 'توصيات المهندس', 'multiline'),
                ]],
            ]),
        ];
    }

    /** @return array<string, string> */
    private function employment(): array
    {
        return ['working' => 'يعمل', 'retired' => 'متقاعد', 'not_working' => 'لا يعمل'];
    }

    /** @param array<string, string> $choices
     * @param  array<int, array<string, mixed>>  $children
     * @param  array<string, string>|null  $visible
     * @param  array<int, string>  $exclusive
     * @return array<string, mixed>
     */
    public function field(string $key, string $label, string $type = 'text', array $choices = [], bool $required = false, array $children = [], ?array $visible = null, array $exclusive = []): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'required' => $required,
            'choices' => collect($choices)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all(),
            'children' => $children, 'visible_when' => $visible, 'exclusive_choices' => $exclusive];
    }

    /** @param array<string, array<mixed>> $rules
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    public function define(string $module, string $title, bool $requiresRecord, array $rules, array $sections): array
    {
        $sections = array_map(function (array $section) use ($rules): array {
            $section['fields'] = array_map(fn (array $field): array => $this->constraints($field, $rules), $section['fields']);

            return $section;
        }, $sections);
        $definition = ['module' => $module, 'title' => $title, 'requires_record' => $requiresRecord, 'sections' => $sections, 'rules' => $rules];
        $definition['version'] = hash('sha256', json_encode($definition, JSON_THROW_ON_ERROR));

        return $definition;
    }

    /** @param array<string, mixed> $field
     * @param  array<string, array<mixed>>  $rules
     * @return array<string, mixed>
     */
    private function constraints(array $field, array $rules, string $prefix = ''): array
    {
        $path = $prefix.$field['key'];
        $fieldRules = $rules[$path] ?? [];
        $field['required'] = in_array('required', $fieldRules, true);
        $field['max_length'] = in_array($field['type'], ['date', 'datetime'], true) ? 100 : (in_array($field['type'], ['text', 'phone', 'multiline'], true) ? 10000 : null);
        $field['min'] = null;
        $field['max'] = null;
        $field['max_items'] = $field['type'] === 'repeat' ? ($path === 'resident_households' ? 100 : 20) : 20;
        foreach ($fieldRules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'max:')) {
                if (in_array($field['type'], ['integer', 'decimal'], true)) {
                    $field['max'] = (float) substr($rule, 4);
                } else {
                    $field['max_length'] = (int) substr($rule, 4);
                }
            }
            if (is_string($rule) && str_starts_with($rule, 'min:')) {
                $field['min'] = (float) substr($rule, 4);
            }
            if (is_string($rule) && str_starts_with($rule, 'between:')) {
                [$field['min'], $field['max']] = array_map('floatval', explode(',', substr($rule, 8)));
            }
        }
        $field['children'] = array_map(fn (array $child): array => $this->constraints($child, $rules, $path.'.*.'), $field['children']);

        return $field;
    }
}
