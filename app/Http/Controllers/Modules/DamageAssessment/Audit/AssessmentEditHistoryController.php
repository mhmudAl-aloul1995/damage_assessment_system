<?php

namespace App\Http\Controllers\Modules\DamageAssessment\Audit;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentEditHistory;
use Illuminate\Http\Request;

class AssessmentEditHistoryController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'global_id' => ['required', 'string'],
            'type' => ['required', 'in:building_table,housing_table'],
            'field_name' => ['required', 'string'],
        ]);

        $label = Assessment::query()
            ->where('name', $validated['field_name'])
            ->value('label');

        $histories = AssessmentEditHistory::query()
            ->with(['user'])
            ->where('global_id', $validated['global_id'])
            ->where('type', $validated['type'])
            ->where('field_name', $validated['field_name'])
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->map(fn (AssessmentEditHistory $history): array => [
                'field_name' => $history->field_name,
                'label' => $label,
                'old_value' => $history->old_value,
                'new_value' => $history->new_value,
                'edited_by' => $history->user?->name ?? '-',
                'created_at' => $history->created_at?->format('Y-m-d h:i A') ?? '-',
                'source' => $history->source ?? '-',
                'return_request_id' => $history->return_request_id,
            ]);

        return response()->json([
            'status' => true,
            'data' => $histories,
            'message' => $histories->isEmpty() ? 'لا يوجد سجل تعديلات.' : null,
        ]);
    }
}
