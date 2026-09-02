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
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardCardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard-cards.index', [
            'cards' => DashboardCard::query()
                ->with('items')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'sourceBuckets' => $this->sourceBuckets(),
            'statKeys' => $this->statKeys(),
            'operators' => $this->operators(),
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
        $dashboardCard->items()->create($this->itemData($request));

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
     * @return array<int, string>
     */
    private function operators(): array
    {
        return ['=', '!=', '>', '>=', '<', '<=', 'like', 'blank', 'not_blank'];
    }

    /**
     * @return array<string, mixed>
     */
    private function cardData(StoreDashboardCardRequest $request): array
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
    private function itemData(StoreDashboardCardItemRequest $request): array
    {
        return [
            ...$request->safe()->except(['is_active', 'decimal_places', 'sort_order']),
            'decimal_places' => (int) $request->input('decimal_places', 0),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
