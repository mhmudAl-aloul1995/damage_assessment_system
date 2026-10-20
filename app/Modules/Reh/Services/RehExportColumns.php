<?php

namespace App\Modules\Reh\Services;

use App\Models\User;

class RehExportColumns
{
    /** @return array<string, array{label: string, group: string, permission?: string}> */
    public static function definitions(): array
    {
        return [
            'unit_reference' => ['label' => 'معرّف الوحدة', 'group' => 'الوحدة والمبنى'],
            'building_reference' => ['label' => 'معرّف المبنى', 'group' => 'الوحدة والمبنى'],
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

    public static function available(User $user): array
    {
        return array_filter(self::definitions(), fn (array $column): bool => ! isset($column['permission']) || $user->can($column['permission']));
    }

    public static function labels(array $keys): array
    {
        return array_map(fn (string $key): string => self::definitions()[$key]['label'], $keys);
    }
}
