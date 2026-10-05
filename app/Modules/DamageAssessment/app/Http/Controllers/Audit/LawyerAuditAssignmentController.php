<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\LawyerAuditAssignment;
use App\Support\Audit\RestrictedLawyerAuditAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Yajra\DataTables\Facades\DataTables;

class LawyerAuditAssignmentController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(RestrictedLawyerAuditAccess::canViewAssignments($request->user()), 403);

        if ($request->ajax()) {
            $query = LawyerAuditAssignment::query()->orderBy('excel_index');
            $lawyerName = RestrictedLawyerAuditAccess::restrictedLawyerNameFor($request->user());

            if ($lawyerName !== null) {
                $query->where('lawyer_name', $lawyerName);
            }

            return DataTables::eloquent($query)
                ->addColumn('assessment_url', fn (LawyerAuditAssignment $assignment): string => $assignment->assessmentUrl())
                ->addColumn('action', function (LawyerAuditAssignment $assignment): string {
                    return '<a class="btn btn-sm btn-light-primary" target="_blank" rel="noopener" href="'.e($assignment->assessmentUrl()).'">فتح الوحدة</a>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return View::make('damage-assessment::audit.lawyerAssignments', [
            'restrictedLawyerName' => RestrictedLawyerAuditAccess::restrictedLawyerNameFor($request->user()),
            'ranges' => RestrictedLawyerAuditAccess::ranges(),
        ]);
    }
}
