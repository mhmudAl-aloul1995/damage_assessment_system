<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDashboardCardItemRequest;
use App\Http\Requests\Admin\StoreDashboardCardRequest;
use App\Http\Requests\Admin\UpdateDashboardCardItemRequest;
use App\Http\Requests\Admin\UpdateDashboardCardRequest;
use App\Models\DashboardCard;
use App\Models\DashboardCardItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardCardController extends Controller
{
    public function index(): View
    {
        $cards = DashboardCard::query()
            ->with('items')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $selectedCard = $cards->firstWhere('id', (int) request('card')) ?? $cards->first();

        return view('admin.dashboard-cards.index', [
            'cards' => $cards,
            'selectedCard' => $selectedCard,
            'nextItemSortOrder' => $selectedCard ? $this->nextItemSortOrder($selectedCard) : 1,
            'sourceBuckets' => $this->sourceBuckets(),
            'statKeys' => $this->statKeys(),
            'filterFields' => $this->filterFields(),
            'operators' => $this->operators(),
            'icons' => $this->icons(),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.dashboard-cards.index');
    }

    public function store(StoreDashboardCardRequest $request): RedirectResponse
    {
        DashboardCard::query()->create($this->cardData($request));

        return redirect()
            ->route('admin.dashboard-cards.index')
            ->with('success', 'تمت إضافة البطاقة بنجاح.');
    }

    public function show(DashboardCard $dashboardCard): RedirectResponse
    {
        return redirect()->route('admin.dashboard-cards.index', ['card' => $dashboardCard->id]);
    }

    public function edit(DashboardCard $dashboardCard): RedirectResponse
    {
        return redirect()->route('admin.dashboard-cards.index', ['card' => $dashboardCard->id]);
    }

    public function update(UpdateDashboardCardRequest $request, DashboardCard $dashboardCard): RedirectResponse
    {
        $dashboardCard->update($this->cardData($request));

        return redirect()
            ->route('admin.dashboard-cards.index', ['card' => $dashboardCard->id])
            ->with('success', 'تم تحديث البطاقة بنجاح.');
    }

    public function destroy(DashboardCard $dashboardCard): RedirectResponse
    {
        $dashboardCard->delete();

        return redirect()
            ->route('admin.dashboard-cards.index')
            ->with('success', 'تم حذف البطاقة بنجاح.');
    }

    public function storeItem(StoreDashboardCardItemRequest $request, DashboardCard $dashboardCard): RedirectResponse
    {
        $data = $this->itemData($request);
        $data['stat_key'] = ($data['stat_key'] ?? null) ?: (($data['filter_field'] ?? null) ?: 'count');
        $data['key'] = ($data['key'] ?? null) ?: $this->uniqueItemKey($dashboardCard, (string) $data['stat_key']);
        $data['sort_order'] = $request->filled('sort_order')
            ? (int) $data['sort_order']
            : $this->nextItemSortOrder($dashboardCard);

        $dashboardCard->items()->create($data);

        return redirect()
            ->route('admin.dashboard-cards.index', ['card' => $dashboardCard->id])
            ->with('success', 'تمت إضافة البند بنجاح.');
    }

    public function updateItem(
        UpdateDashboardCardItemRequest $request,
        DashboardCard $dashboardCard,
        DashboardCardItem $dashboardCardItem
    ): RedirectResponse {
        abort_unless($dashboardCardItem->dashboard_card_id === $dashboardCard->id, 404);

        $dashboardCardItem->update($this->itemData($request));

        return redirect()
            ->route('admin.dashboard-cards.index', ['card' => $dashboardCard->id])
            ->with('success', 'تم تحديث البند بنجاح.');
    }

    public function destroyItem(DashboardCard $dashboardCard, DashboardCardItem $dashboardCardItem): RedirectResponse
    {
        abort_unless($dashboardCardItem->dashboard_card_id === $dashboardCard->id, 404);

        $dashboardCardItem->delete();

        return redirect()
            ->route('admin.dashboard-cards.index', ['card' => $dashboardCard->id])
            ->with('success', 'تم حذف البند بنجاح.');
    }

    public function filterValues(Request $request): JsonResponse
    {
        $sourceBucket = (string) $request->string('source_bucket');
        $field = (string) $request->string('field');
        $table = $this->sourceTables()[$sourceBucket] ?? null;

        if ($table === null || $field === '' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $field)) {
            return response()->json(['values' => []]);
        }

        $values = DB::table($table)
            ->whereNotNull($field)
            ->where($field, '<>', '')
            ->distinct()
            ->orderBy($field)
            ->limit(200)
            ->pluck($field)
            ->map(fn (mixed $value): string => (string) $value)
            ->values();

        return response()->json(['values' => $values]);
    }

    /**
     * @return array<int, string>
     */
    private function sourceBuckets(): array
    {
        return [
            'buildingStats',
            'unitStats',
            'publicBuildingStats',
            'roadFacilityStats',
            'csoSurveyStats',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function statKeys(): array
    {
        return [
            'buildingStats' => ['assessed_total', 'not_completed', 'completed', 'pending', 'fully_damaged', 'partially_damaged', 'committee_review', 'no_damage', 'unclassified', 'assessment_obstacle', 'uxo', 'bodies', 'debris'],
            'unitStats' => ['total_units', 'fully_damaged', 'partially_damaged', 'damaged_total', 'committee_review', 'no_damage', 'unclassified', 'has_fire', 'has_strip', 'habitable', 'security_unsafe', 'unit_stripping', 'unit_support_needed'],
            'publicBuildingStats' => ['total_surveys', 'damaged_buildings', 'total_units', 'municipalities', 'neighborhoods', 'assigned_staff', 'occupied_buildings', 'bodies_present', 'uxo_present'],
            'roadFacilityStats' => ['total_surveys', 'damaged_roads', 'undamaged_roads', 'completed_road_length_km', 'total_items', 'municipalities', 'neighborhoods', 'potholes_locations', 'obstacle_locations', 'buried_bodies_locations', 'uxo_locations'],
            'csoSurveyStats' => ['total_surveys', 'completed', 'damaged_buildings', 'total_organizations', 'total_units', 'without_units', 'without_organization', 'municipalities', 'neighborhoods', 'assessment_blocked'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function filterFields(): array
    {
        return collect($this->sourceTables())
            ->mapWithKeys(fn (string $table, string $sourceBucket): array => [
                $sourceBucket => Schema::hasTable($table)
                    ? collect(Schema::getColumnListing($table))
                        ->reject(fn (string $column): bool => in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'], true))
                        ->values()
                        ->all()
                    : [],
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function sourceTables(): array
    {
        return [
            'buildingStats' => 'buildings',
            'unitStats' => 'housing_units',
            'publicBuildingStats' => 'public_building_surveys',
            'roadFacilityStats' => 'road_facility_surveys',
            'csoSurveyStats' => 'cso_surveys',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function operators(): array
    {
        return ['=', '!=', '>', '>=', '<', '<=', 'like', 'blank', 'not_blank'];
    }

    /**
     * @return array<string, string>
     */
    private function icons(): array
    {
        return [
            'ki-home-2' => 'مبنى',
            'ki-home' => 'وحدة سكنية',
            'ki-people' => 'أشخاص',
            'ki-office-bag' => 'مؤسسة',
            'ki-route' => 'طريق',
            'ki-shield-cross' => 'ضرر',
            'ki-rescue' => 'دعم',
            'ki-questionnaire-tablet' => 'لجنة',
            'ki-check-circle' => 'مكتمل',
            'ki-cross-circle' => 'غير مكتمل',
            'ki-flash-circle' => 'خطر',
            'ki-bucket' => 'حفرة',
            'ki-element-11' => 'عناصر',
            'ki-map' => 'خريطة',
            'ki-geolocation' => 'موقع',
            'ki-profile-user' => 'مستخدم',
            'ki-question' => 'سؤال',
            'ki-category' => 'تصنيف',
            'ki-dot' => 'نقطة',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cardData(FormRequest $request): array
    {
        return [
            ...$request->safe()->except('is_active'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemData(FormRequest $request): array
    {
        $conditions = $this->itemConditions($request);
        $primaryCondition = $conditions[0] ?? null;
        $data = [
            ...$request->safe()->except(['is_active', 'decimal_places', 'sort_order', 'conditions']),
            'icon' => $request->input('icon') ?: 'ki-dot',
            'decimal_places' => (int) $request->input('decimal_places', 0),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active'),
            'filter_field' => $primaryCondition['field'] ?? null,
            'filter_operator' => $primaryCondition['operator'] ?? null,
            'filter_value' => $primaryCondition['value'] ?? null,
            'options' => [
                'conditions' => $conditions,
            ],
        ];

        $data['stat_key'] = ($data['stat_key'] ?? null) ?: (($data['filter_field'] ?? null) ?: 'count');

        return $data;
    }

    /**
     * @return array<int, array{field: string, operator: string, value: ?string}>
     */
    private function itemConditions(FormRequest $request): array
    {
        $conditions = collect($request->input('conditions', []))
            ->map(function (mixed $condition): array {
                $condition = is_array($condition) ? $condition : [];

                return [
                    'field' => trim((string) ($condition['field'] ?? '')),
                    'operator' => trim((string) ($condition['operator'] ?? '=')),
                    'value' => trim((string) ($condition['value'] ?? '')),
                ];
            })
            ->filter(fn (array $condition): bool => $condition['field'] !== '')
            ->map(function (array $condition): array {
                if (in_array($condition['operator'], ['blank', 'not_blank'], true)) {
                    $condition['value'] = null;
                }

                if ($condition['operator'] === '') {
                    $condition['operator'] = '=';
                }

                return $condition;
            })
            ->values()
            ->all();

        if ($conditions !== []) {
            return $conditions;
        }

        $field = trim((string) $request->input('filter_field', ''));

        if ($field === '') {
            return [];
        }

        $operator = trim((string) $request->input('filter_operator', '='));

        return [[
            'field' => $field,
            'operator' => $operator !== '' ? $operator : '=',
            'value' => in_array($operator, ['blank', 'not_blank'], true)
                ? null
                : trim((string) $request->input('filter_value', '')),
        ]];
    }

    private function uniqueItemKey(DashboardCard $dashboardCard, string $baseKey): string
    {
        $key = $baseKey !== '' ? $baseKey : 'item';
        $candidate = $key;
        $counter = 2;

        while ($dashboardCard->items()->where('key', $candidate)->exists()) {
            $candidate = $key.'_'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function nextItemSortOrder(DashboardCard $dashboardCard): int
    {
        $lastSortOrder = (int) $dashboardCard->items()->max('sort_order');

        return $lastSortOrder + 1;
    }
}
