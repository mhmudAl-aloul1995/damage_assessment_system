<?php

namespace App\Http\Controllers;

use App\Exports\FieldEngineerReportExport;
use App\Http\Requests\Report\FieldEngineerReportFilterRequest;
use App\Services\FieldEngineerReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class FieldEngineerReportController extends Controller
{
    public function __construct(private readonly FieldEngineerReportService $fieldEngineerReportService)
    {
        $this->middleware('role:Database Officer|Project Officer|Area Manager|Auditing Supervisor');
    }

    public function index(FieldEngineerReportFilterRequest $request): Response
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());

        return response()->view('reports.field-engineer.index', [
            'filters' => $filters,
            'filterOptions' => $this->fieldEngineerReportService->filterOptions(),
            'summary' => $this->fieldEngineerReportService->summary($filters),
        ]);
    }

    public function buildings(FieldEngineerReportFilterRequest $request): JsonResponse
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        $query = $this->fieldEngineerReportService->filteredBuildingsQuery($filters);

        return DataTables::of($query)
            ->editColumn('final_status_label', fn ($row) => $this->statusBadge($row->final_status_name, $row->final_status_label))
            ->editColumn('creationdate', fn ($row) => $row->creationdate ? date('Y-m-d h:i A', strtotime((string) $row->creationdate)) : '-')
            ->editColumn('editdate', fn ($row) => $row->editdate ? date('Y-m-d h:i A', strtotime((string) $row->editdate)) : '-')
            ->rawColumns(['final_status_label'])
            ->toJson();
    }

    public function housingUnits(FieldEngineerReportFilterRequest $request): JsonResponse
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        $query = $this->fieldEngineerReportService->filteredHousingUnitsQuery($filters);

        return DataTables::of($query)
            ->editColumn('creationdate', fn ($row) => $row->creationdate ? date('Y-m-d h:i A', strtotime((string) $row->creationdate)) : '-')
            ->toJson();
    }

    public function edits(FieldEngineerReportFilterRequest $request): JsonResponse
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        $query = $this->fieldEngineerReportService->filteredEditsQuery($filters);

        return DataTables::of($query)
            ->editColumn('source_type', fn ($row) => $row->source_type === 'building_table'
                ? __('multilingual.field_engineer_report.types.building')
                : __('multilingual.field_engineer_report.types.housing'))
            ->editColumn('field_name', fn ($row) => $this->fieldEngineerReportService->fieldLabel((string) $row->field_name))
            ->editColumn('updated_at', fn ($row) => $row->updated_at ? date('Y-m-d h:i A', strtotime((string) $row->updated_at)) : '-')
            ->toJson();
    }

    public function statusHistory(FieldEngineerReportFilterRequest $request): JsonResponse
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        $query = $this->fieldEngineerReportService->filteredStatusHistoryQuery($filters);

        return DataTables::of($query)
            ->editColumn('item_type', fn ($row) => $row->item_type === 'building'
                ? __('multilingual.field_engineer_report.types.building')
                : __('multilingual.field_engineer_report.types.housing'))
            ->editColumn('status_label', fn ($row) => $this->statusBadge($row->status_name, $row->status_label))
            ->editColumn('created_at', fn ($row) => $row->created_at ? date('Y-m-d h:i A', strtotime((string) $row->created_at)) : '-')
            ->rawColumns(['status_label'])
            ->toJson();
    }

    public function assignments(FieldEngineerReportFilterRequest $request): JsonResponse
    {
        $filters = $this->fieldEngineerReportService->normalizeFilters($request->validated());
        $query = $this->fieldEngineerReportService->filteredAssignmentsQuery($filters);

        return DataTables::of($query)
            ->editColumn('assigned_date', fn ($row) => $row->assigned_date ? date('Y-m-d h:i A', strtotime((string) $row->assigned_date)) : '-')
            ->toJson();
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
}
