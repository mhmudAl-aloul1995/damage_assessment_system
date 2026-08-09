<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\MissingCitizenIdentityReport;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

class MissingCitizenIdentityController extends Controller
{
    public function index(): ViewContract
    {
        return View::make('damage-assessment::reports.missing-citizen-identities', [
            'totalMissingCitizenIdentities' => MissingCitizenIdentityReport::query()->count(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $perPage = 25;
        $afterId = max(0, $request->integer('after_id', 0));
        $search = trim($request->string('search')->toString());

        $query = MissingCitizenIdentityReport::query()
            ->select(['id', 'owner_name', 'id_number']);

        if ($search !== '') {
            if (ctype_digit($search)) {
                $query->where('id_number', 'like', $search.'%');
            } else {
                $query->where('owner_name', 'like', '%'.$search.'%');
            }
        }

        $total = (clone $query)->count();

        $query->when($afterId > 0, fn (Builder $query): Builder => $query->where('id', '>', $afterId));

        /** @var Collection<int, MissingCitizenIdentityReport> $rows */
        $rows = $query
            ->orderBy('id')
            ->limit($perPage + 1)
            ->get();

        return response()->json([
            'data' => $rows
                ->take($perPage)
                ->map(fn (MissingCitizenIdentityReport $report): array => [
                    'id' => $report->id,
                    'owner_name' => $report->owner_name ?: '-',
                    'id_number1' => (string) $report->id_number,
                ])
                ->values(),
            'has_more' => $rows->count() > $perPage,
            'next_cursor' => $rows->take($perPage)->last()?->id,
            'total' => $total,
        ]);
    }
}
