<?php

namespace App\Http\Controllers\Report;

use App\Exports\DamageStatisticsReportExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class DamageStatisticsReportController extends Controller
{
    public function index()
    {
        $municipalities = DB::table('buildings')
            ->whereNotNull('municipalitie')
            ->where('municipalitie', '!=', '')
            ->distinct()
            ->orderBy('municipalitie')
            ->pluck('municipalitie');

        $neighborhoods = DB::table('buildings')
            ->whereNotNull('neighborhood')
            ->where('neighborhood', '!=', '')
            ->distinct()
            ->orderBy('neighborhood')
            ->pluck('neighborhood');

        $fieldEngineers = DB::table('buildings')
            ->whereNotNull('assignedto')
            ->where('assignedto', '!=', '')
            ->distinct()
            ->orderBy('assignedto')
            ->pluck('assignedto');

        $damageStatuses = DB::table('buildings')
            ->whereNotNull('building_damage_status')
            ->where('building_damage_status', '!=', '')
            ->distinct()
            ->orderBy('building_damage_status')
            ->pluck('building_damage_status');

        return view('reports.damage_statistics.index', compact(
            'municipalities',
            'neighborhoods',
            'fieldEngineers',
            'damageStatuses'
        ));
    }

    public function data(Request $request)
    {
        return response()->json([
            'data' => $this->buildReportRows($request),
        ]);
    }

    public function export(Request $request)
    {
        $rows = $this->buildReportRows($request);

        return Excel::download(
            new DamageStatisticsReportExport($rows),
            'damage_statistics_report_' . now()->format('Y_m_d_His') . '.xlsx'
        );
    }

    private function buildReportRows(Request $request): array
    {
        $buildings = $this->buildingsQuery($request);
        $housingUnits = $this->housingUnitsQuery($request);

        $teamsCount = (clone $buildings)
            ->whereNotNull('b.assignedto')
            ->where('b.assignedto', '!=', '')
            ->distinct('b.assignedto')
            ->count('b.assignedto');
        $totalUnits = (clone $housingUnits)->count();

        $workingDays = (clone $buildings)
            ->whereNotNull('b.editdate')
            ->selectRaw('COUNT(DISTINCT DATE(b.editdate)) as days_count')
            ->value('days_count');

        $averagePerTeamPerDay = 0;

        if ($teamsCount > 0 && $workingDays > 0) {
            $averagePerTeamPerDay = round($totalUnits / $teamsCount / $workingDays, 2);
        }

        $totalTeamsPerDay = round($averagePerTeamPerDay * $teamsCount, 2);
        return [
            $this->section('احصائيات المباني والوحدات السكنية في مناطق العمل'),

            $this->row(1, 'عدد المباني السكنية في مناطق العمل', DB::table('buildings')->count(), 'مبنى سكني'),
            $this->row(2, 'عدد الوحدات السكنية في مناطق العمل', DB::table('housing_units')->count(), 'وحدة سكنية'),

            $this->section('احصائيات المباني والوحدات السكنية التي تم حصرها حسب تاريخ التعديل'),

            $this->row(3, 'عدد المباني السكنية التى تم حصرها', (clone $buildings)->count(), 'مبنى سكني'),
            $this->row(4, 'عدد الوحدات السكنية التى تم حصرها', (clone $housingUnits)->count(), 'وحدة سكنية'),

            $this->section('تفاصيل احصائيات حصر اضرار المباني والوحدات السكنية في مناطق العمل'),

            $this->row(5, 'مباني سكنية ضرر كلي', (clone $buildings)->whereIn('b.building_damage_status', $this->fullDamageValues())->count(), 'مبنى سكني'),
            $this->row(6, 'مباني سكنية ضرر جزئي', (clone $buildings)->whereIn('b.building_damage_status', $this->partialDamageValues())->count(), 'مبنى سكني'),
            $this->row(7, 'مباني سكنية بحاجة إلى لجنة فنية', (clone $buildings)->whereIn('b.building_damage_status', $this->committeeValues())->count(), 'مبنى سكني'),
            $this->row(8, 'مباني سكنية تواجه إعاقة في التقييم', (clone $buildings)->whereIn('b.assessment_obstacle', $this->obstacleValues())->count(), 'مبنى سكني'),

            $this->row(9, 'وحدات سكنية ضرر كلي', (clone $housingUnits)->whereIn('hu.unit_damage_status', $this->fullDamageValues())->count(), 'وحدة سكنية'),
            $this->row(10, 'وحدات سكنية ضرر جزئي', (clone $housingUnits)->whereIn('hu.unit_damage_status', $this->partialDamageValues())->count(), 'وحدة سكنية'),
            $this->row(11, 'وحدات سكنية بحاجة إلى لجنة فنية', (clone $housingUnits)->whereIn('hu.unit_damage_status', $this->committeeValues())->count(), 'وحدة سكنية'),
            $this->row(12, 'وحدات سكنية تواجه إعاقة في التقييم', (clone $housingUnits)->whereIn('hu.security_situation_unit', ['Unsafe'])->count(), 'وحدة سكنية'),

            $this->section('تفاصيل عمل فرق الحصر اليومي'),

            $this->row(13, 'عدد مهندسي الحصر', $teamsCount * 2, 'فريق'),
            $this->row(14, 'ينجز المهندس في اليوم تقريبا', (int) $averagePerTeamPerDay, 'وحدة سكنية'),
            $this->row(15, 'تنجز كافة المهندسين في اليوم تقريبا', (int) $totalTeamsPerDay, 'وحدة سكنية'),
        ];
    }

    private function buildingsQuery(Request $request)
    {
        $query = DB::table('buildings as b');

        $this->applyFilters($query, $request);

        return $query;
    }

    private function housingUnitsQuery(Request $request)
    {
        $query = DB::table('housing_units as hu')
            ->leftJoin('buildings as b', 'b.globalid', '=', 'hu.parentglobalid');

        $this->applyFilters($query, $request);

        return $query;
    }

    private function applyFilters($query, Request $request): void
    {
        // التقرير حسب تاريخ التعديل من جدول buildings فقط


        $query->where('b.field_status', "COMPLETED");

        if ($request->filled('from_date')) {
            $query->whereDate('b.editdate', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('b.editdate', '<=', $request->to_date);
        }

        if ($request->filled('municipalitie')) {
            $query->where('b.municipalitie', $request->municipalitie);
        }

        if ($request->filled('neighborhood')) {
            $query->where('b.neighborhood', $request->neighborhood);
        }

        if ($request->filled('assignedto')) {
            $query->where('b.assignedto', $request->assignedto);
        }

        if ($request->filled('building_damage_status')) {
            $query->where('b.building_damage_status', $request->building_damage_status);
        }

    }

    private function section(string $title): array
    {
        return [
            'no' => null,
            'description' => $title,
            'count' => null,
            'notes' => null,
            'is_section' => true,
        ];
    }

    private function row(int $no, string $description, int|float|string|null $count, string $notes): array
    {
        return [
            'no' => $no,
            'description' => $description,
            'count' => $count,
            'notes' => $notes,
            'is_section' => false,
        ];
    }

    private function fullDamageValues(): array
    {
        return [
            'fully_damaged',
            'fully_damaged2',
            'totally_damaged',
            'total_damage',
            'full_damage',
            'كلي',
            'ضرر كلي',
            'هدم كلي',
        ];
    }

    private function partialDamageValues(): array
    {
        return [
            'partially_damaged',
            'partially_damaged2',
            'partial_damage',
            'partial',
            'جزئي',
            'ضرر جزئي',
        ];
    }

    private function committeeValues(): array
    {
        return [
            'committee_review',
            'committee_review2',
            'need_committee',
            'needs_committee',
            'technical_committee',
            'بحاجة الي لجنة فنية',
            'بحاجة إلى لجنة فنية',
        ];
    }

    private function obstacleValues(): array
    {
        return [
            'yes',
            'obstacle',
            'has_obstacle',
            'inaccessible',
            'تواجه اعاقة',
            'تواجه إعاقة',
            'تواجه اعاقة في التقييم',
            'تواجه إعاقة في التقييم',
        ];
    }
}