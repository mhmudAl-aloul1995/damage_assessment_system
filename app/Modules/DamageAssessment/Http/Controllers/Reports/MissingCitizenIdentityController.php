<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\HousingUnit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class MissingCitizenIdentityController extends Controller
{
    public function index(): ViewContract
    {
        return View::make('damage-assessment::reports.missing-citizen-identities');
    }

    public function data(Request $request): JsonResponse
    {
        $perPage = 25;
        $page = max(1, $request->integer('page', 1));
        $search = trim($request->string('search')->toString());

        $query = $this->missingCitizenIdentityQuery();

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('housing_units.unit_owner', 'like', '%'.$search.'%')
                    ->orWhere('housing_units.id_number1', 'like', '%'.$search.'%');
            });
        }

        /** @var Collection<int, HousingUnit> $rows */
        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage + 1)
            ->get();

        return response()->json([
            'data' => $rows
                ->take($perPage)
                ->map(fn (HousingUnit $housingUnit): array => [
                    'owner_name' => $housingUnit->unit_owner ?: '-',
                    'id_number1' => (string) $housingUnit->id_number1,
                ])
                ->values(),
            'has_more' => $rows->count() > $perPage,
            'page' => $page,
        ]);
    }

    private function missingCitizenIdentityQuery(): Builder
    {
        return HousingUnit::query()
            ->select([
                'housing_units.id',
                'housing_units.unit_owner',
                'housing_units.id_number1',
            ])
            ->whereNotNull('housing_units.id_number1')
            ->where('housing_units.id_number1', '<>', '')
            ->whereNotExists(function ($query): void {
                $query
                    ->select(DB::raw(1))
                    ->from($this->citizensTable().' as citizens')
                    ->whereColumn('citizens.id_card_no', 'housing_units.id_number1')
                    ->where('citizens.status', 'A');
            })
            ->orderBy('housing_units.id_number1');
    }

    private function citizensTable(): string
    {
        if (app()->environment('testing')) {
            return 'citizens';
        }

        return 'phc_dashboard.citizens';
    }
}
