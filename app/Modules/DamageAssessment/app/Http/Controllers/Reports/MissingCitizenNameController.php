<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\MissingCitizenNameReport;
use App\Support\ArabicNameNormalizer;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

class MissingCitizenNameController extends Controller
{
    public function index(): ViewContract
    {
        return View::make('damage-assessment::reports.missing-citizen-names', [
            'totalMissingCitizenNames' => MissingCitizenNameReport::query()->count(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $perPage = 25;
        $afterId = max(0, $request->integer('after_id', 0));
        $search = trim($request->string('search')->toString());

        $query = MissingCitizenNameReport::query()
            ->select(['id', 'owner_name', 'normalized_owner_name']);

        if ($search !== '') {
            $normalizedSearch = ArabicNameNormalizer::normalize($search);

            $query->where(function (Builder $query) use ($search, $normalizedSearch): void {
                $query->where('owner_name', 'like', '%'.$search.'%');

                if ($normalizedSearch !== '') {
                    $query->orWhere('normalized_owner_name', 'like', $normalizedSearch.'%');
                }
            });
        }

        $total = (clone $query)->count();

        $query->when($afterId > 0, fn (Builder $query): Builder => $query->where('id', '>', $afterId));

        /** @var Collection<int, MissingCitizenNameReport> $rows */
        $rows = $query
            ->orderBy('id')
            ->limit($perPage + 1)
            ->get();

        return response()->json([
            'data' => $rows
                ->take($perPage)
                ->map(fn (MissingCitizenNameReport $report): array => [
                    'id' => $report->id,
                    'owner_name' => $report->owner_name ?: '-',
                    'normalized_owner_name' => $report->normalized_owner_name ?: '-',
                ])
                ->values(),
            'has_more' => $rows->count() > $perPage,
            'next_cursor' => $rows->take($perPage)->last()?->id,
            'total' => $total,
        ]);
    }
}
