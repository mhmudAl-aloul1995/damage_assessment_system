<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\HousingUnit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Yajra\DataTables\Facades\DataTables;

class MissingCitizenIdentityController extends Controller
{
    public function index(): ViewContract
    {
        return View::make('damage-assessment::reports.missing-citizen-identities', [
            'missingCount' => $this->missingCitizenIdentityQuery()->count(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return DataTables::eloquent($this->missingCitizenIdentityQuery())
            ->filter(function (Builder $query) use ($request): void {
                $search = trim((string) data_get($request->input('search'), 'value', ''));

                if ($search === '') {
                    return;
                }

                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('housing_units.unit_owner', 'like', '%'.$search.'%')
                        ->orWhere('housing_units.id_number1', 'like', '%'.$search.'%');
                });
            })
            ->addColumn('owner_name', fn (HousingUnit $housingUnit): string => $housingUnit->unit_owner ?: '-')
            ->editColumn('id_number1', fn (HousingUnit $housingUnit): string => (string) $housingUnit->id_number1)
            ->toJson();
    }

    private function missingCitizenIdentityQuery(): Builder
    {
        return HousingUnit::query()
            ->select([
                'housing_units.id',
                'housing_units.unit_owner',
                'housing_units.id_number1',
            ])
            ->leftJoin($this->citizensTable().' as citizens', function (JoinClause $join): void {
                $join
                    ->on('citizens.id_card_no', '=', 'housing_units.id_number1')
                    ->where('citizens.status', '=', 'A');
            })
            ->whereNotNull('housing_units.id_number1')
            ->where('housing_units.id_number1', '<>', '')
            ->whereNull('citizens.id_card_no')
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
