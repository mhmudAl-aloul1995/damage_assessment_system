<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DashboardCard;
use App\Models\DashboardCardItem;
use Illuminate\Database\Seeder;

class DashboardCardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect($this->cards())->each(function (array $cardData): void {
            $items = $cardData['items'];
            unset($cardData['items']);

            $card = DashboardCard::query()->updateOrCreate(
                ['key' => $cardData['key']],
                $cardData
            );

            collect($items)->each(function (array $itemData) use ($card): void {
                DashboardCardItem::query()->updateOrCreate(
                    [
                        'dashboard_card_id' => $card->id,
                        'key' => $itemData['key'],
                    ],
                    [
                        ...$itemData,
                        'dashboard_card_id' => $card->id,
                    ]
                );
            });
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cards(): array
    {
        return [
            [
                'key' => 'buildings',
                'title' => 'المباني',
                'subtitle' => 'مباني تم تقييمها',
                'source_bucket' => 'buildingStats',
                'total_stat_key' => 'assessed_total',
                'icon' => 'ki-home-2',
                'color' => '#ad3d3d',
                'sort_order' => 10,
                'items' => [
                    $this->item('fully_damaged', 'ضرر كلي', 'buildingStats', 'fully_damaged', 'ki-shield-cross', 'buildings', 'fully_damaged', 10, 'building_damage_status', '=', 'fully_damaged'),
                    $this->item('partially_damaged', 'ضرر جزئي', 'buildingStats', 'partially_damaged', 'ki-rescue', 'buildings', 'partially_damaged', 20, 'building_damage_status', '=', 'partially_damaged'),
                    $this->item('committee_review', 'لجنة فنية', 'buildingStats', 'committee_review', 'ki-questionnaire-tablet', 'buildings', 'committee_review', 30, 'building_damage_status', '=', 'committee_review'),
                    $this->item('assessment_obstacle', 'يوجد عائق', 'buildingStats', 'assessment_obstacle', 'ki-check-circle', 'buildings', 'assessment_blocked', 40, 'assessment_obstacle', '=', 'yes'),
                    $this->item('bodies', 'وجود جثث', 'buildingStats', 'bodies', 'ki-people', 'buildings', 'bodies_present', 50, 'bodies_present', '=', 'yes3'),
                    $this->item('uxo', 'ذخائر غير منفجرة', 'buildingStats', 'uxo', 'ki-cross-circle', 'buildings', 'uxo_present', 60, 'uxo_present', '=', 'yes3'),
                    $this->item('debris', 'ركام يعيق الوصول', 'buildingStats', 'debris', 'ki-route', 'buildings', 'debris_blocking', 70, 'building_debris_blocking', '=', 'yes'),
                    $this->item('completed', 'مكتمل', 'buildingStats', 'completed', 'ki-check-circle', 'buildings', 'completed', 80, 'field_status', '=', 'COMPLETED'),
                ],
            ],
            [
                'key' => 'housing',
                'title' => 'الوحدات السكنية',
                'subtitle' => 'إجمالي الوحدات السكنية',
                'source_bucket' => 'unitStats',
                'total_stat_key' => 'total_units',
                'icon' => 'ki-home',
                'color' => '#67986c',
                'sort_order' => 20,
                'options' => ['target' => 67500],
                'items' => [
                    $this->item('fully_damaged', 'ضرر كلي', 'unitStats', 'fully_damaged', 'ki-shield-cross', 'housing', 'fully_damaged', 10, 'unit_damage_status', '=', 'fully_damaged2'),
                    $this->item('partially_damaged', 'ضرر جزئي', 'unitStats', 'partially_damaged', 'ki-rescue', 'housing', 'partially_damaged', 20, 'unit_damage_status', '=', 'partially_damaged2'),
                    $this->item('committee_review', 'لجنة فنية', 'unitStats', 'committee_review', 'ki-questionnaire-tablet', 'housing', 'committee_review', 30, 'unit_damage_status', '=', 'committee_review2'),
                    $this->item('no_damage', 'لا يوجد ضرر', 'unitStats', 'no_damage', 'ki-check-circle', 'housing', 'no_damage', 40, 'unit_damage_status', '=', 'no_damaged'),
                    $this->item('security_unsafe', 'تدعيم هيكلي', 'unitStats', 'security_unsafe', 'ki-question', 'housing', 'assessment_blocked', 50, 'security_situation_unit', '=', 'yes'),
                    $this->item('unit_support_needed', 'قابل للانهيار', 'unitStats', 'unit_support_needed', 'ki-rescue', 'housing', 'structural_support', 60, 'unit_support_needed', '=', 'yes'),
                    $this->item('unit_stripping', 'متأثرة بالحريق', 'unitStats', 'unit_stripping', 'ki-cross-circle', 'housing', 'at_risk_of_collapse', 70, 'unit_stripping', '=', 'yes'),
                    $this->item('habitable', 'مناسبة للسكن', 'unitStats', 'habitable', 'ki-home', 'housing', 'habitable', 80, 'is_the_housing_unit_or_living_habitable', '=', 'yes'),
                    $this->item('has_fire', 'متأثرة بالحريق', 'unitStats', 'has_fire', 'ki-flash-circle', 'housing', 'fire_affected', 90, 'has_fire', '=', 'yes'),
                ],
            ],
            [
                'key' => 'cso_surveys',
                'title' => 'منظمات المجتمع المدني',
                'subtitle' => 'إجمالي استبيانات المنظمات',
                'source_bucket' => 'csoSurveyStats',
                'total_stat_key' => 'total_surveys',
                'icon' => 'ki-people',
                'color' => '#315f72',
                'sort_order' => 30,
                'items' => [
                    $this->item('completed', 'مكتمل', 'csoSurveyStats', 'completed', 'ki-check-circle', 'cso_surveys', 'completed', 10, 'field_status', '=', 'COMPLETED'),
                    $this->item('damaged_buildings', 'متضررة', 'csoSurveyStats', 'damaged_buildings', 'ki-shield-cross', 'cso_surveys', 'damaged', 20, 'building_damage_status', 'not_blank', null),
                    $this->item('total_organizations', 'المنظمات', 'csoSurveyStats', 'total_organizations', 'ki-people', 'cso_surveys', 'organizations', 30),
                    $this->item('total_units', 'الوحدات', 'csoSurveyStats', 'total_units', 'ki-home-2', 'cso_surveys', 'units', 40),
                    $this->item('without_units', 'بدون وحدات', 'csoSurveyStats', 'without_units', 'ki-home-cross', 'cso_surveys', 'without_units', 50),
                    $this->item('without_organization', 'بدون منظمة', 'csoSurveyStats', 'without_organization', 'ki-people', 'cso_surveys', 'without_organization', 60),
                    $this->item('municipalities', 'البلديات', 'csoSurveyStats', 'municipalities', 'ki-map', 'cso_surveys', 'index', 70),
                    $this->item('neighborhoods', 'الأحياء', 'csoSurveyStats', 'neighborhoods', 'ki-geolocation', 'cso_surveys', 'index', 80),
                    $this->item('assessment_blocked', 'يوجد عائق', 'csoSurveyStats', 'assessment_blocked', 'ki-profile-user', 'cso_surveys', 'assessment_blocked', 90),
                ],
            ],
            [
                'key' => 'public_buildings',
                'title' => 'المباني العامة',
                'subtitle' => 'إجمالي المباني العامة',
                'source_bucket' => 'publicBuildingStats',
                'total_stat_key' => 'total_surveys',
                'icon' => 'ki-office-bag',
                'color' => '#c8a400',
                'sort_order' => 40,
                'items' => [
                    $this->item('damaged_buildings', 'متضررة', 'publicBuildingStats', 'damaged_buildings', 'ki-shield-cross', 'public_buildings', 'damaged', 10, 'building_damage_status', 'not_blank', null),
                    $this->item('total_units', 'الوحدات', 'publicBuildingStats', 'total_units', 'ki-home-2', 'public_buildings', 'units', 20),
                    $this->item('municipalities', 'البلديات', 'publicBuildingStats', 'municipalities', 'ki-map', 'public_buildings', 'municipalities', 30),
                    $this->item('neighborhoods', 'الأحياء', 'publicBuildingStats', 'neighborhoods', 'ki-geolocation', 'public_buildings', 'neighborhoods', 40),
                    $this->item('assigned_staff', 'الطاقم المكلف', 'publicBuildingStats', 'assigned_staff', 'ki-people', 'public_buildings', 'assigned_staff', 50),
                    $this->item('occupied_buildings', 'مشغولة', 'publicBuildingStats', 'occupied_buildings', 'ki-people', 'public_buildings', 'occupied', 60, 'is_building_occupied', '=', 'yes'),
                    $this->item('bodies_present', 'جثث', 'publicBuildingStats', 'bodies_present', 'ki-people', 'public_buildings', 'bodies', 70, 'is_bodies', '=', 'yes'),
                    $this->item('uxo_present', 'ذخائر', 'publicBuildingStats', 'uxo_present', 'ki-cross-circle', 'public_buildings', 'uxo', 80, 'is_uxo', '=', 'yes'),
                ],
            ],
            [
                'key' => 'road_facilities',
                'title' => 'الطرق',
                'subtitle' => 'إجمالي الطرق',
                'source_bucket' => 'roadFacilityStats',
                'total_stat_key' => 'total_surveys',
                'icon' => 'ki-route',
                'color' => '#0f766e',
                'sort_order' => 50,
                'items' => [
                    $this->item('damaged_roads', 'مقيّمة', 'roadFacilityStats', 'damaged_roads', 'ki-route', 'road_facilities', 'damaged', 10, 'road_damage_level', 'not_blank', null),
                    $this->item('undamaged_roads', 'تعيق التقييم', 'roadFacilityStats', 'undamaged_roads', 'ki-check-circle', 'road_facilities', 'undamaged', 20, 'security_situation', '=', 'unsafe'),
                    $this->item('completed_road_length_km', 'طول الشوارع', 'roadFacilityStats', 'completed_road_length_km', 'ki-route', null, null, 30, null, null, null, 'كم', 3),
                    $this->item('total_items', 'جدول الكميات', 'roadFacilityStats', 'total_items', 'ki-element-11', 'road_facilities', 'items', 40),
                    $this->item('municipalities', 'البلديات', 'roadFacilityStats', 'municipalities', 'ki-map', 'road_facilities', 'municipalities', 50),
                    $this->item('neighborhoods', 'الأحياء', 'roadFacilityStats', 'neighborhoods', 'ki-geolocation', 'road_facilities', 'neighborhoods', 60),
                    $this->item('potholes_locations', 'الحفر', 'roadFacilityStats', 'potholes_locations', 'ki-bucket', 'road_facilities', 'potholes', 70, 'potholes_exist', '=', 'yes'),
                    $this->item('buried_bodies_locations', 'الجثث المدفونة', 'roadFacilityStats', 'buried_bodies_locations', 'ki-people', 'road_facilities', 'buried_bodies', 80, 'buried_bodies', '=', 'yes'),
                    $this->item('uxo_locations', 'ذخائر', 'roadFacilityStats', 'uxo_locations', 'ki-cross-circle', 'road_facilities', 'uxo', 90, 'uxo_present', '=', 'yes'),
                ],
            ],
        ];
    }

    private function item(
        string $key,
        string $title,
        string $sourceBucket,
        string $statKey,
        string $icon,
        ?string $linkGroup,
        ?string $linkKey,
        int $sortOrder,
        ?string $filterField = null,
        ?string $filterOperator = null,
        ?string $filterValue = null,
        ?string $valueSuffix = null,
        int $decimalPlaces = 0
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'source_bucket' => $sourceBucket,
            'stat_key' => $statKey,
            'icon' => $icon,
            'link_group' => $linkGroup,
            'link_key' => $linkKey,
            'calculation_type' => 'stat_key',
            'filter_field' => $filterField,
            'filter_operator' => $filterOperator,
            'filter_value' => $filterValue,
            'value_suffix' => $valueSuffix,
            'decimal_places' => $decimalPlaces,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ];
    }
}
