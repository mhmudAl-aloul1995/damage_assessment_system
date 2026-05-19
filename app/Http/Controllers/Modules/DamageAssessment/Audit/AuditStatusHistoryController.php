<?php

namespace App\Http\Controllers\Modules\DamageAssessment\Audit;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingStatus;
use App\Models\BuildingStatusHistory;
use App\Models\HousingStatus;
use App\Models\HousingStatusHistory;
use App\Models\HousingUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AuditStatusHistoryController extends Controller
{

    public function buildingHistory(Request $request): JsonResponse
    {
        $building = Building::where('globalid', $request->globalid)->first();

        if (!$building) {
            return response()->json([
                'status' => false,
                'history' => [],
            ]);
        }

        $canDelete = auth()->user()->hasAnyRole([
            'Database Officer',
            'Auditing Supervisor',
        ]);

        /*
        |--------------------------------------------------------------------------
        | First try BuildingStatusHistory
        |--------------------------------------------------------------------------
        */

        $history = BuildingStatusHistory::with(['user.roles', 'status'])
            ->where('building_id', $building->objectid)

            ->orderByDesc('created_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | If no history found -> fallback to BuildingStatus
        |--------------------------------------------------------------------------
        */

        if ($history->isEmpty()) {

            $history = BuildingStatus::with(['user.roles', 'status'])
                ->where('building_id', $building->objectid)

                ->orderByDesc('created_at')
                ->get()
                ->map(function ($item) {

                    $statusName = $item->status->name ?? '-';
                    $statusLabel = $item->status->label_en ?? $statusName;
                    $roleName = $item->user?->roles?->first()?->name ?? '-';

                    return [
                        'id' => 'status_' . $item->id,
                        'source' => 'building_status',
                        ...$this->statusPayload($statusName, $statusLabel, $roleName),
                        'user_name' => $item->user->name ?? '-',
                        'role_name' => $roleName,
                        'notes' => $item->notes,
                        'created_at' => $item->created_at?->format('Y-m-d h:i A') ?? '-',
                        'can_delete' => false,
                    ];
                });

        } else {

            $history = $history->map(function ($item) use ($canDelete) {

                $statusName = $item->status->name ?? '-';
                $statusLabel = $item->status->label_en ?? $statusName;
                $roleName = $item->user?->roles?->first()?->name ?? '-';

                return [
                    'id' => 'history_' . $item->id,
                    'source' => 'building_history',
                    ...$this->statusPayload($statusName, $statusLabel, $roleName),
                    'user_name' => $item->user->name ?? '-',
                    'role_name' => $roleName,
                    'notes' => $item->notes,
                    'created_at' => $item->created_at?->format('Y-m-d h:i A') ?? '-',
                    'can_delete' => $canDelete,
                ];
            });

        }

        return response()->json([
            'status' => true,
            'history' => $history->values(),
        ]);
    }


    public function housingHistory(Request $request): Collection|array
    {
        $housing = HousingUnit::where('globalid', $request->globalid)->first();

        if (!$housing) {
            return [];
        }

        $histories = HousingStatusHistory::with(['user.roles', 'assessment_status'])
            ->where('housing_id', $housing->objectid)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->orderByDesc('created_at')
            ->get();

        if ($histories->isNotEmpty()) {
            return $histories
                ->map(function ($item) {

                    $statusName = $item->assessment_status->name ?? '-';
                    $statusLabel = $item->assessment_status->label_en ?? $statusName;
                    $roleName = $item->user?->roles?->first()?->name ?? '-';

                    return [
                        'id' => 'history_' . $item->id,
                        'source' => 'housing_history',
                        ...$this->statusPayload($statusName, $statusLabel, $roleName),
                        'user_name' => $item->user->name ?? '-',
                        'role_name' => $roleName,
                        'notes' => $item->notes,
                        'created_at' => $item->created_at?->format('Y-m-d h:i A') ?? '-',
                    ];
                })
                ->values();
        }

        return HousingStatus::with(['user.roles', 'assessment_status'])
            ->where('housing_id', $housing->objectid)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($item) {

                $statusName = $item->assessment_status->name ?? '-';
                $statusLabel = $item->assessment_status->label_en ?? $statusName;
                $roleName = $item->user?->roles?->first()?->name ?? '-';

                return [
                    'id' => 'status_' . $item->id,
                    'source' => 'housing_status',
                    ...$this->statusPayload($statusName, $statusLabel, $roleName),
                    'user_name' => $item->user->name ?? '-',
                    'role_name' => $roleName,
                    'notes' => $item->notes,
                    'created_at' => $item->created_at?->format('Y-m-d h:i A') ?? '-',
                ];
            })
            ->values();
    }

    /**
     * @return array{status_name: string, status_label: string, status_badge_class: string}
     */
    private function statusPayload(?string $statusName, ?string $statusLabel, ?string $roleName): array
    {
        $label = $statusLabel ?: ($statusName ?: '-');

        return [
            'status_name' => $label,
            'status_label' => $label,
            'status_badge_class' => $this->getStatusBadge($statusName ?: '-', $roleName),
        ];
    }

    private function getStatusBadge(string $statusName, ?string $role = null): string
    {
        return match ($statusName) {
            'assigned_to_lawyer' => 'badge badge-light-primary fw-bold',
            'assigned_to_engineer' => 'badge badge-light-primary fw-bold',
            'accepted_by_engineer',
            'accepted' => 'badge badge-light-success fw-bold',
            'rejected_by_engineer',
            'rejected' => 'badge badge-light-danger fw-bold',
            'need_review' => 'badge badge-light-warning fw-bold',
            'legal_notes' => 'badge badge-light-primary fw-bold',
            default => 'badge badge-light-secondary fw-bold',
        };
    }

    /**
     * @return array<int, string>
     */

    public function getEditableNote(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:building,housing',
            'globalid' => 'required|string',
        ]);

        $user = auth()->user();

        $noteType = null;

        if ($user->hasRole('Legal Auditor')) {
            $noteType = 'Legal Auditor';
        } elseif ($user->hasRole('QC/QA Engineer')) {
            $noteType = 'QC/QA Engineer';
        } elseif ($user->hasRole('Engineering Auditor')) {
            $noteType = 'Engineering Auditor';
        }

        $type = $request->type;
        $globalid = $request->globalid;

        /*
        |--------------------------------------------------------------------------
        | BUILDING
        |--------------------------------------------------------------------------
        */

        if ($type === 'building') {

            $building = Building::where('globalid', $globalid)->first();

            if (!$building) {
                return response()->json([
                    'message' => 'المبنى غير موجود',
                ], 404);
            }

            $hasFinalApprove = BuildingStatusHistory::where('building_id', $building->objectid)
                ->whereHas('status', function ($q) {
                    $q->where('name', 'final_approval');
                })
                ->exists();

            /*
            |--------------------------------------------------------------------------
            | First check BuildingStatusHistory
            |--------------------------------------------------------------------------
            */

            $note = BuildingStatusHistory::with(['status', 'user'])
                ->where('building_id', $building->objectid)
                ->whereNotNull('notes')
                ->where('notes', '!=', '')
                ->when($noteType, function ($query) use ($noteType) {
                    $query->where('type', $noteType);
                })
                ->latest('id')
                ->first();

            $source = 'building_history';

            /*
            |--------------------------------------------------------------------------
            | If empty -> check BuildingStatus
            |--------------------------------------------------------------------------
            */

            if (!$note) {

                $note = BuildingStatus::with(['status', 'user'])
                    ->where('building_id', $building->objectid)
                    ->whereNotNull('notes')
                    ->where('notes', '!=', '')
                    ->when($noteType, function ($query) use ($noteType) {
                        $query->where('type', $noteType);
                    })
                    ->latest('id')
                    ->first();

                $source = 'building_status';
            }

            if (!$note) {
                return response()->json([
                    'message' => 'لا توجد ملاحظة متاحة',
                ], 404);
            }

            return response()->json([
                'id' => $note->id,
                'source' => $source,
                'notes' => $note->notes,
                'has_final_approve' => $hasFinalApprove,
                'status_name' => optional($note->status)->label_en
                    ?? optional($note->status)->name
                    ?? '-',
                'user_name' => optional($note->user)->name ?? '-',
                'role_name' => $note->type ?? '-',
                'created_at' => optional($note->created_at)?->format('Y-m-d H:i'),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | HOUSING
        |--------------------------------------------------------------------------
        */

        if ($type === 'housing') {

            $housing = HousingUnit::where('globalid', $globalid)->first();

            if (!$housing) {
                return response()->json([
                    'message' => 'الوحدة السكنية غير موجودة',
                ], 404);
            }

            $hasFinalApprove = HousingStatusHistory::where('housing_id', $housing->objectid)
                ->whereHas('assessment_status', function ($q) {
                    $q->where('name', 'final_approval');
                })
                ->exists();

            /*
            |--------------------------------------------------------------------------
            | First check HousingStatusHistory
            |--------------------------------------------------------------------------
            */

            $note = HousingStatusHistory::with(['assessment_status', 'user'])
                ->where('housing_id', $housing->objectid)
                ->whereNotNull('notes')
                ->where('notes', '!=', '')
                ->when($noteType, function ($query) use ($noteType) {
                    $query->where('type', $noteType);
                })
                ->latest('id')
                ->first();

            $source = 'housing_history';

            /*
            |--------------------------------------------------------------------------
            | If empty -> check HousingStatus
            |--------------------------------------------------------------------------
            */

            if (!$note) {

                $note = HousingStatus::with(['assessment_status', 'user'])
                    ->where('housing_id', $housing->objectid)
                    ->whereNotNull('notes')
                    ->where('notes', '!=', '')
                    ->when($noteType, function ($query) use ($noteType) {
                        $query->where('type', $noteType);
                    })
                    ->latest('id')
                    ->first();

                $source = 'housing_status';
            }

            if (!$note) {
                return response()->json([
                    'message' => 'لا توجد ملاحظة متاحة',
                ], 404);
            }

            return response()->json([
                'id' => $note->id,
                'source' => $source,
                'notes' => $note->notes,
                'has_final_approve' => $hasFinalApprove,
                'status_name' => optional($note->assessment_status)->label_en
                    ?? optional($note->assessment_status)->name
                    ?? '-',
                'user_name' => optional($note->user)->name ?? '-',
                'role_name' => $note->type ?? '-',
                'created_at' => optional($note->created_at)?->format('Y-m-d H:i'),
            ]);
        }

        return response()->json([
            'message' => 'نوع غير صحيح',
        ], 422);
    }
    public function updateNote(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer',
            'type' => 'required|in:building,housing',
            'notes' => 'nullable|string',
        ]);


        $id = $request->id;
        $type = $request->type;
        $notes = trim((string) $request->notes);

        if ($type === 'building') {
            $note = BuildingStatusHistory::find($id);

            if (!$note) {
                return response()->json([
                    'message' => 'الملاحظة غير موجودة',
                ], 404);
            }

            $hasFinalApprove = BuildingStatusHistory::where('building_id', $note->building_id)
                ->whereHas('status', function ($q) {
                    $q->where('name', 'final_approval');
                })
                ->exists();

            if ($hasFinalApprove) {
                return response()->json([
                    'message' => 'لا يمكن تعديل الملاحظة لأن الاعتماد النهائي موجود',
                ], 422);
            }

            $note->notes = $notes;
            $note->save();

            return response()->json([
                'message' => 'تم تحديث ملاحظة المبنى بنجاح',
            ]);
        }

        if ($type === 'housing') {
            $note = HousingStatusHistory::find($id);

            if (!$note) {
                return response()->json([
                    'message' => 'الملاحظة غير موجودة',
                ], 404);
            }

            $hasFinalApprove = HousingStatusHistory::where('housing_id', $note->housing_id)
                ->whereHas('assessment_status', function ($q) {
                    $q->where('name', 'final_approval');
                })
                ->exists();

            if ($hasFinalApprove) {
                return response()->json([
                    'message' => 'لا يمكن تعديل الملاحظة لأن الاعتماد النهائي موجود',
                ], 422);
            }

            $note->notes = $notes;
            $note->save();

            return response()->json([
                'message' => 'تم تحديث ملاحظة الوحدة بنجاح',
            ]);
        }
    }

    public function deleteHistory(Request $request): JsonResponse
    {
        $history = BuildingStatusHistory::with('status')->find($request->id);

        if (!$history) {
            return response()->json([
                'status' => false,
                'message' => 'السجل غير موجود',
            ]);
        }
        if (!auth()->user()->hasAnyRole(['Database Officer', 'Auditing Supervisor'])) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بحذف هذا السجل',
            ], 403);
        }
        $isLast = BuildingStatusHistory::where('building_id', $history->building_id)->count() <= 1;
        if ($isLast) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن حذف آخر حالة',
            ]);
        }
        if (in_array($history->status->name ?? '', ['final_approval', 'final_rejected'])) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن حذف الحالة النهائية',
            ]);
        }

        $history->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف السجل بنجاح',
        ]);
    }
}
