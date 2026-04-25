<?php

namespace App\Http\Controllers;

use App\Exports\FieldEngineerReportExport;
use App\Http\Requests\Report\FieldEngineerReportFilterRequest;
use App\Services\FieldEngineerReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class FieldEngineerReportController extends Controller
{
    public function __construct(private readonly FieldEngineerReportService $fieldEngineerReportService)
    {
        $this->middleware('role:Database Officer|Project Officer|Area Manager|Auditing Supervisor');
    }

    public function index(FieldEngineerReportFilterRequest $request): Response
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        $startedAt = microtime(true);

        $response = response()->view('reports.field-engineer.index', [
            'filters' => $filters,
            'filterOptions' => $this->fieldEngineerReportService->filterOptions(),
            'summary' => $filters['assignedto']
                ? $this->fieldEngineerReportService->summary($filters)
                : $this->fieldEngineerReportService->emptySummary(),
        ]);

        Log::info('FieldEngineerReport index time', [
            'assignedto' => $filters['assignedto'],
            'execution_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        return $response;
    }

    public function buildings(FieldEngineerReportFilterRequest $request): JsonResponse
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        $parameters = $this->dataTableParameters($request);

        if (! $filters['assignedto']) {
            return $this->emptyDataTableResponse($parameters['draw']);
        }

        $startedAt = microtime(true);
        $result = $this->fieldEngineerReportService->paginateBuildings($filters, $parameters['start'], $parameters['length']);
        $data = $result['rows']->map(fn ($row) => [
            'objectid' => $row->objectid,
            'globalid' => $row->globalid,
            'assignedto' => $row->assignedto,
            'municipalitie' => $row->municipalitie,
            'neighborhood' => $row->neighborhood,
            'parcel_no1' => $row->parcel_no1,
            'building_use' => $row->building_use,
            'building_damage_status' => $row->building_damage_status,
            'creationdate' => $row->creationdate ? date('Y-m-d h:i A', strtotime((string) $row->creationdate)) : '-',
            'editdate' => $row->editdate ? date('Y-m-d h:i A', strtotime((string) $row->editdate)) : '-',
            'final_status_label' => $this->statusBadge($row->final_status_name, $row->final_status_label),
        ]);

        $response = response()->json($this->dataTablePayload($parameters['draw'], $result['total'], $data->all()));

        $this->logEndpointTiming('buildings', $filters, $startedAt);

        return $response;
    }

    public function housingUnits(FieldEngineerReportFilterRequest $request): JsonResponse
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        $parameters = $this->dataTableParameters($request);

        if (! $filters['assignedto']) {
            return $this->emptyDataTableResponse($parameters['draw']);
        }

        $startedAt = microtime(true);
        $result = $this->fieldEngineerReportService->paginateHousingUnits($filters, $parameters['start'], $parameters['length']);
        $data = $result['rows']->map(fn ($row) => [
            'objectid' => $row->objectid,
            'parentglobalid' => $row->parentglobalid,
            'building_objectid' => $row->building_objectid,
            'housing_unit_type' => $row->housing_unit_type,
            'unit_damage_status' => $row->unit_damage_status,
            'occupied' => $row->occupied,
            'creationdate' => $row->creationdate ? date('Y-m-d h:i A', strtotime((string) $row->creationdate)) : '-',
        ]);

        $response = response()->json($this->dataTablePayload($parameters['draw'], $result['total'], $data->all()));

        $this->logEndpointTiming('housing_units', $filters, $startedAt);

        return $response;
    }

    public function edits(FieldEngineerReportFilterRequest $request): JsonResponse
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        $parameters = $this->dataTableParameters($request);

        if (! $filters['assignedto']) {
            return $this->emptyDataTableResponse($parameters['draw']);
        }

        $startedAt = microtime(true);
        $result = $this->fieldEngineerReportService->paginateEdits($filters, $parameters['start'], $parameters['length']);
        $data = $result['rows']->map(fn ($row) => [
            'source_type' => $row->source_type === 'building_table'
                ? __('multilingual.field_engineer_report.types.building')
                : __('multilingual.field_engineer_report.types.housing'),
            'global_id' => $row->global_id,
            'field_name' => $this->fieldEngineerReportService->fieldLabel((string) $row->field_name),
            'old_value' => $row->old_value,
            'new_value' => $row->new_value,
            'updated_by' => $row->updated_by,
            'updated_at' => $row->updated_at ? date('Y-m-d h:i A', strtotime((string) $row->updated_at)) : '-',
        ]);

        $response = response()->json($this->dataTablePayload($parameters['draw'], $result['total'], $data->all()));

        $this->logEndpointTiming('edits', $filters, $startedAt);

        return $response;
    }

    public function statusHistory(FieldEngineerReportFilterRequest $request): JsonResponse
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        $parameters = $this->dataTableParameters($request);

        if (! $filters['assignedto']) {
            return $this->emptyDataTableResponse($parameters['draw']);
        }

        $startedAt = microtime(true);
        $result = $this->fieldEngineerReportService->paginateStatusHistory($filters, $parameters['start'], $parameters['length']);
        $data = $result['rows']->map(fn ($row) => [
            'item_type' => $row->item_type === 'building'
                ? __('multilingual.field_engineer_report.types.building')
                : __('multilingual.field_engineer_report.types.housing'),
            'item_number' => $row->item_number,
            'status_label' => $this->statusBadge($row->status_name, $row->status_label),
            'changed_by' => $row->changed_by,
            'created_at' => $row->created_at ? date('Y-m-d h:i A', strtotime((string) $row->created_at)) : '-',
        ]);

        $response = response()->json($this->dataTablePayload($parameters['draw'], $result['total'], $data->all()));

        $this->logEndpointTiming('status_history', $filters, $startedAt);

        return $response;
    }

    public function assignments(FieldEngineerReportFilterRequest $request): JsonResponse
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        $parameters = $this->dataTableParameters($request);

        if (! $filters['assignedto']) {
            return $this->emptyDataTableResponse($parameters['draw']);
        }

        $startedAt = microtime(true);
        $result = $this->fieldEngineerReportService->paginateAssignments($filters, $parameters['start'], $parameters['length']);
        $data = $result['rows']->map(fn ($row) => [
            'building_id' => $row->building_id,
            'assigned_user' => $row->assigned_user,
            'assigned_by' => $row->assigned_by,
            'assigned_date' => $row->assigned_date ? date('Y-m-d h:i A', strtotime((string) $row->assigned_date)) : '-',
            'notes' => $row->notes,
        ]);

        $response = response()->json($this->dataTablePayload($parameters['draw'], $result['total'], $data->all()));

        $this->logEndpointTiming('assignments', $filters, $startedAt);

        return $response;
    }

    public function stats(FieldEngineerReportFilterRequest $request): JsonResponse
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        $startedAt = microtime(true);

        $response = response()->json([
            'summary' => $filters['assignedto']
                ? $this->fieldEngineerReportService->summary($filters)
                : $this->fieldEngineerReportService->emptySummary(),
        ]);

        Log::info('FieldEngineerReport stats time', [
            'assignedto' => $filters['assignedto'],
            'execution_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        return $response;
    }

    public function export(FieldEngineerReportFilterRequest $request, string $tab, string $format)
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        [$headings, $rows] = $this->fieldEngineerReportService->exportRows($tab, $filters);

        abort_if($headings === [] || ! in_array($format, ['xlsx', 'csv'], true), 404);

        $extension = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;

        return Excel::download(
            new FieldEngineerReportExport($headings, $rows->map(fn ($row) => is_array($row) ? $row : (array) $row)->all()),
            "field-engineer-report-{$tab}.{$format}",
            $extension
        );
    }

    private function statusBadge(?string $statusName, ?string $statusLabel): string
    {
        $resolvedStatus = strtolower((string) $statusName);
        $badgeClass = 'badge badge-light-secondary fw-bold';

        if (str_contains($resolvedStatus, 'accept')) {
            $badgeClass = 'badge badge-light-success fw-bold';
        } elseif (str_contains($resolvedStatus, 'reject')) {
            $badgeClass = 'badge badge-light-danger fw-bold';
        } elseif ($resolvedStatus === 'need_review') {
            $badgeClass = 'badge badge-light-warning fw-bold';
        }

        return '<span class="'.$badgeClass.'">'.e($statusLabel ?: '-').'</span>';
    }

    private function emptyDataTableResponse(int $draw): JsonResponse
    {
        return response()->json($this->dataTablePayload($draw, 0, []));
    }

    private function logEndpointTiming(string $endpoint, array $filters, float $startedAt): void
    {
        Log::info('FieldEngineerReport datatable time', [
            'endpoint' => $endpoint,
            'assignedto' => $filters['assignedto'],
            'execution_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);
    }

    private function dataTableParameters(Request $request): array
    {
        $length = max(1, min((int) $request->input('length', 25), 100));

        return [
            'draw' => (int) $request->input('draw', 1),
            'start' => max((int) $request->input('start', 0), 0),
            'length' => $length,
        ];
    }

    private function dataTablePayload(int $draw, int $total, array $data): array
    {
        return [
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        ];
    }
}
