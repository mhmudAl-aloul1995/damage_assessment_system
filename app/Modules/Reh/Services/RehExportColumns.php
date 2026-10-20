<?php

namespace App\Modules\Reh\Services;

use App\Models\User;

class RehExportColumns
{
    private static ?array $definitions = null;

    public static function summaryColumns(): array
    {
        return [
            'unit_reference' => ['label' => 'معرّف حصر الوحدة', 'group' => 'الوحدة والمبنى'],
            'building_reference' => ['label' => 'معرّف حصر المبنى', 'group' => 'الوحدة والمبنى'],
            'owner' => ['label' => 'المالك', 'group' => 'الوحدة والمبنى'],
            'identity' => ['label' => 'الهوية', 'group' => 'الوحدة والمبنى', 'permission' => 'reh.sensitive'],
            'governorate' => ['label' => 'المحافظة', 'group' => 'الموقع'],
            'neighborhood' => ['label' => 'الحي', 'group' => 'الموقع'],
            'workflow' => ['label' => 'التدقيق', 'group' => 'المتابعة والتدقيق'],
            'decision' => ['label' => 'القرار', 'group' => 'المتابعة والتدقيق'],
            'pricing' => ['label' => 'التسعير', 'group' => 'المتابعة والتدقيق'],
            'synced_at' => ['label' => 'آخر مزامنة', 'group' => 'المتابعة والتدقيق'],
            'engineering_score' => ['label' => 'نقاط هندسية محسومة', 'group' => 'التقييم والأولوية'],
            'engineering_pending' => ['label' => 'فئات هندسية معلقة', 'group' => 'التقييم والأولوية'],
            'total_score' => ['label' => 'الدرجة النهائية', 'group' => 'التقييم والأولوية', 'permission' => 'reh.scores.view'],
            'priority' => ['label' => 'الأولوية', 'group' => 'التقييم والأولوية', 'permission' => 'reh.scores.view'],
            'scoring_version' => ['label' => 'إصدار قواعد الحساب', 'group' => 'التقييم والأولوية'],
            'unit_code' => ['label' => 'كود الوحدة', 'group' => 'الوحدة والمبنى'],
            'building_name' => ['label' => 'اسم المبنى', 'group' => 'الوحدة والمبنى'],
        ];
    }

    public static function definitions(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }
        $columns = self::summaryColumns();
        foreach (app(RehSurveyFilters::class)->definitions() as $key => $field) {
            $label = $field['label'];
            if (preg_match('/^additional_item_quantity_([123])$/', $field['field'], $matches)) {
                $label = 'كمية البند الإضافي '.$matches[1];
            }
            $group = preg_replace('/[\p{So}\x{FE0F}\x{200D}]/u', '', $field['context'] ?: $field['title']);
            $columns[$key] = [
                'label' => $label, 'group' => ($field['scope'] === 'building' ? 'المبنى' : 'الوحدة').' · '.trim($group),
                'scope' => $field['scope'], 'field' => $field['field'], 'column' => $field['column'],
            ];
            if ($field['sensitive']) {
                $columns[$key]['permission'] = 'reh.sensitive';
            }
        }

        return self::$definitions = $columns;
    }

    public static function defaults(): array
    {
        return array_keys(self::summaryColumns());
    }

    public static function version(): string
    {
        return substr(hash('sha256', implode('|', array_keys(self::definitions()))), 0, 10);
    }

    public static function decode(string $encoded): array
    {
        $length = (int) ceil(count(self::definitions()) / 4);
        if (! preg_match('/^'.self::version().'\.([a-f0-9]{'.$length.'})$/D', $encoded, $matches)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['columns' => 'تعذر استعادة اختيار الأعمدة. حدّث صفحة التصدير وأعد الاختيار.']);
        }
        $keys = array_keys(self::definitions());
        $selected = [];
        for ($index = 0; $index < $length * 4; $index++) {
            if ((hexdec($matches[1][intdiv($index, 4)]) & (1 << ($index % 4))) !== 0) {
                if (! isset($keys[$index])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['columns' => 'اختيار أعمدة غير صالح.']);
                }
                $selected[] = $keys[$index];
            }
        }

        return $selected;
    }

    public static function catalog(User $user): array
    {
        return ['version' => self::version(), 'length' => (int) ceil(count(self::definitions()) / 4), 'indexes' => array_intersect_key(array_flip(array_keys(self::definitions())), self::available($user))];
    }

    public static function available(User $user): array
    {
        return array_filter(self::definitions(), fn (array $column): bool => ! isset($column['permission']) || $user->can($column['permission']));
    }

    public static function labels(array $keys): array
    {
        $definitions = self::definitions();

        return array_map(fn (string $key): string => $definitions[$key]['label'], $keys);
    }
}
