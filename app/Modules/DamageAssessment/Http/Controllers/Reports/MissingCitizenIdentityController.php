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
        $afterId = max(0, $request->integer('after_id', 0));
        $search = trim($request->string('search')->toString());

        $query = $this->missingCitizenIdentityQuery()
            ->when($afterId > 0, fn (Builder $query): Builder => $query->where('housing_units.id', '>', $afterId));

        if ($search !== '') {
            if (ctype_digit($search)) {
                $query->where('housing_units.id_number1', 'like', $search.'%');
            } else {
                $query->where('housing_units.unit_owner', 'like', '%'.$search.'%');
            }
        }

        /** @var Collection<int, HousingUnit> $rows */
        $rows = $query
            ->limit($perPage + 1)
            ->get();

        return response()->json([
            'data' => $rows
                ->take($perPage)
                ->map(fn (HousingUnit $housingUnit): array => [
                    'id' => $housingUnit->id,
                    'owner_name' => $housingUnit->unit_owner ?: '-',
                    'id_number1' => (string) $housingUnit->id_number1,
                ])
                ->values(),
            'has_more' => $rows->count() > $perPage,
            'next_cursor' => $rows->take($perPage)->last()?->id,
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
            ->orderBy('housing_units.id');
    }

    private function citizensTable(): string
    {
        if (app()->environment('testing')) {
            return 'citizens';
        }

        return 'phc_dashboard.citizens';
    }
}
