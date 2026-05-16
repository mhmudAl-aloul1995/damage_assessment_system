<?php

namespace App\Http\Controllers\Modules\DamageAssessment\Audit;

use App\Http\Controllers\Controller;
use App\Models\AssessmentEditHistory;
use App\Services\AssessmentEditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentInlineEditController extends Controller
{
    public function update(Request $request, AssessmentEditService $assessmentEditService): JsonResponse
    {
        $request->merge([
            'globalid' => $request->input('globalid', $request->input('global_id')),
        ]);

        $request->validate([
            'type' => 'required|in:building_table,housing_table',
            'globalid' => 'required|string',
            'field' => 'required|string',
            'value' => 'nullable',
        ]);

        $result = $assessmentEditService->save(
            (string) $request->type,
            (string) $request->globalid,
            (string) $request->field,
            $request->value,
            $request
        );

        if (! $result['changed']) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Ù„Ø§ ÙŠÙˆØ¬Ø¯ ØªØºÙŠÙŠØ± ÙÙŠ Ø§Ù„Ù‚ÙŠÙ…Ø©.',
                'history' => $this->historyRows($request->type, $request->globalid, $request->field),
            ]);
        }

        $edit = $result['edit'];

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'ØªÙ… Ø­ÙØ¸ Ø§Ù„ØªØ¹Ø¯ÙŠÙ„ Ø¨Ù†Ø¬Ø§Ø­',
            'edit_id' => $edit->id,
            'field_value' => $edit->field_value,
            'user_name' => $edit->user?->name ?? '-',
            'updated_at' => $edit->updated_at?->format('Y-m-d h:i A'),
            'history' => $this->historyRows($request->type, $request->globalid, $request->field),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $request->merge([
            'globalid' => $request->input('globalid', $request->input('global_id')),
        ]);

        $request->validate([
            'type' => 'required|in:building_table,housing_table',
            'globalid' => 'required|string',
            'field' => 'required|string',
        ]);

        return response()->json([
            'status' => true,
            'history' => $this->historyRows(
                (string) $request->type,
                (string) $request->globalid,
                (string) $request->field
            ),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historyRows(string $type, string $globalid, string $field): array
    {
        return AssessmentEditHistory::query()
            ->with('user')
            ->where('type', $type)
            ->where('global_id', $globalid)
            ->where('field_name', $field)
            ->latest('created_at')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (AssessmentEditHistory $history): array => [
                'id' => $history->id,
                'value' => $history->new_value,
                'old_value' => $history->old_value,
                'new_value' => $history->new_value,
                'user_name' => $history->user?->name ?? '-',
                'updated_at' => $history->created_at?->format('Y-m-d h:i A') ?? '-',
                'source' => $history->source ?? '-',
                'return_request_id' => $history->return_request_id,
            ])
            ->all();
    }
}
