<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\AssessmentEditHistory;
use App\Models\AssignedAssessmentUser;
use App\Models\Building;
use App\Models\HousingUnit;
use App\Models\User;
use App\Services\AssessmentEditService;
use App\Support\Audit\RestrictedLawyerAuditAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentInlineEditController extends Controller
{
    private const TEMPORARY_HIDDEN_AUDIT_ACTION_USER_NAMES = [
        'ظٹط§ط³ظ…ظٹظ† ظ…ط§ظ‡ط± ظ…طµط·ظپظ‰ ط§ط¨ظˆظ…ط¯ظ„ظ„ط©',
        'ط؛ط§ط¯ط© ظ…ط­ظ…ظˆط¯ ط¹ط¨ط¯ط§ظ„ط­ظٹ ط§ظ„ظ‡ط¨ط§ط´',
        'ط±ط§ظ†ظٹظ‡ ط³ظ„ظٹظ…ط§ظ† ط±ط§ط´ط¯ ط´ط¹طھ',
    ];

    private const TEMPORARY_HIDDEN_AUDIT_ACTION_USER_ID_NUMBERS = [
        '800409062',
        '400940623',
        '803275288',
        '800900607',
        '801773987',
        '405790619',
        '403697311',
        '803307669',
        '404030421',
        '406966812',
        '400591194',
        '404581993',
        '456901503',
        '400662938',
        '403746530',

    ];

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

        $building = $this->buildingForAssessmentEdit((string) $request->type, (string) $request->globalid);

        if (RestrictedLawyerAuditAccess::isRestrictedLawyer($request->user())) {
            abort(403, 'هذا الحساب مخصص للعرض فقط ولا يمكنه تعديل التدقيق أو إضافة ملاحظات.');
        }

        if ($request->user()?->hasRole('Team Leader')) {
            abort(403, 'This assessment is read only.');
        }

        if (
            $request->user()?->hasAnyRole(['Field Engineer', 'field Engineer'])
            && ! $this->canEditAssessmentForBuilding($request->user(), $building)
        ) {
            abort(403, 'هذا الاستبيان متاح للقراءة فقط.');
        }

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

    private function buildingForAssessmentEdit(string $type, string $globalid): ?Building
    {
        if ($type === 'building_table') {
            return Building::query()->where('globalid', $globalid)->first();
        }

        $housingUnit = HousingUnit::query()->where('globalid', $globalid)->first();

        if (! $housingUnit instanceof HousingUnit) {
            return null;
        }

        return Building::query()->where('globalid', $housingUnit->parentglobalid)->first();
    }

    private function canEditAssessmentForBuilding(?User $user, ?Building $building): bool
    {
        if (! $user instanceof User || ! $building instanceof Building) {
            return false;
        }

        if (RestrictedLawyerAuditAccess::isRestrictedLawyer($user)) {
            return false;
        }

        if ($user->hasAnyRole(['Database Officer', 'Auditing Supervisor', 'Audit Reviewer'])) {
            return true;
        }

        if ($this->hasTemporaryStatusAssignmentException($user)) {
            return true;
        }

        $assignmentTypes = [];

        if ($user->hasAnyRole(['QC/QA Engineer', 'Engineering Auditor'])) {
            $assignmentTypes[] = 'QC/QA Engineer';
        }

        if ($user->hasRole('Legal Auditor')) {
            $assignmentTypes[] = 'Legal Auditor';
        }

        if ($assignmentTypes === []) {
            return false;
        }

        return AssignedAssessmentUser::query()
            ->where('building_id', $building->objectid)
            ->where('user_id', $user->id)
            ->whereIn('type', $assignmentTypes)
            ->exists();
    }

    private function hasTemporaryStatusAssignmentException(User $user): bool
    {
        return $user->hasRole('Audit Reviewer')
            || in_array(trim($user->name), self::TEMPORARY_HIDDEN_AUDIT_ACTION_USER_NAMES, true)
            || in_array(trim((string) $user->id_no), self::TEMPORARY_HIDDEN_AUDIT_ACTION_USER_ID_NUMBERS, true);
    }
}
