<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\AttendanceImport;
use App\Models\AttendanceImportLog;
use App\Imports\AttendanceMultiSheetImport;
use App\Imports\AttendanceSheetImport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Exports\AttendanceMonthlyReportExport;
use App\Models\Role;
class AttendanceController extends Controller
{
    public function index()
    {
        return view('Attendance.attendance');
    }


    public function data(Request $request)
    {
        $month = $request->month ?? date('m');
        $year = $request->year ?? date('Y');

        $dateContext = \Carbon\Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $dateContext->daysInMonth;

        $users = \App\Models\User::query()
            ->with([
                'roles',
                'attendances' => function ($query) use ($month, $year) {
                    $query->whereMonth('date', $month)
                        ->whereYear('date', $year);
                }
            ])
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', ['Field Engineer']);
            })
            ->withCount([
                'attendances as total_present' => function ($query) use ($month, $year) {
                    $query->whereMonth('date', $month)
                        ->whereYear('date', $year)
                        ->where('status', 1);
                }
            ]);

        if ($request->filled('contract_type')) {
            $users->where('contract_type', $request->contract_type);
        }

        if ($request->filled('region')) {
            $users->where('region', $request->region);
        }

        $dataTable = datatables()->eloquent($users)
            ->addIndexColumn()
            ->addColumn('id', fn($user) => $user->id)
            ->addColumn('name', fn($user) => $user->name ?? '-')
            ->addColumn('name_en', fn($user) => $user->name_en ?? '-')
            ->addColumn('id_no', fn($user) => $user->id_no ?? '-')
            ->addColumn('phone', fn($user) => $user->phone ?? '-')
            ->addColumn('contract_type', fn($user) => $user->contract_type ?? '-')
            ->addColumn('region', fn($user) => $user->region ?? '-')
            ->addColumn('total', fn($user) => $user->total_present ?? 0);

        for ($i = 1; $i <= 31; $i++) {
            $dataTable->addColumn("day_$i", function ($user) use ($i, $daysInMonth, $year, $month) {
                if ($i > $daysInMonth) {
                    return 'N/A';
                }

                $dateStr = \Carbon\Carbon::createFromDate($year, $month, $i)->format('Y-m-d');

                $record = $user->attendances->first(function ($attendance) use ($dateStr) {
                    return \Carbon\Carbon::parse($attendance->date)->format('Y-m-d') === $dateStr;
                });

                return $record ? (int) $record->status : 0;
            });
        }

        return $dataTable->make(true);
    }
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'status' => 'required|in:0,1',
        ]);

        try {
            $attendance = Attendance::updateOrCreate(
                [
                    'user_id' => $request->user_id,
                    'date' => $request->date,
                ],
                [
                    'status' => $request->status,
                    'updated_by' => auth()->id(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Attendance updated successfully',
                'data' => $attendance,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function setDayPresent(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'role' => 'nullable|string',
            'contract_type' => 'nullable|string',
        ]);

        try {
            $users = User::query()->with('roles');

            // فلترة role
            if ($request->filled('role')) {
                $users->whereHas('roles', function ($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }

            // فلترة contract
            if ($request->filled('contract_type')) {
                $users->where('contract_type', $request->contract_type);
            }

            $users = $users->pluck('id');

            foreach ($users as $userId) {
                Attendance::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'date' => $request->date,
                    ],
                    [
                        'status' => 1,
                        'updated_by' => auth()->id(),
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'All users set to PRESENT for ' . $request->date
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function setDayAbsent(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'role' => 'nullable|string',
            'contract_type' => 'nullable|string',
        ]);

        try {
            $users = User::query()->with('roles');

            if ($request->filled('role')) {
                $users->whereHas('roles', function ($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }

            if ($request->filled('contract_type')) {
                $users->where('contract_type', $request->contract_type);
            }

            $users = $users->pluck('id');

            foreach ($users as $userId) {
                Attendance::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'date' => $request->date,
                    ],
                    [
                        'status' => 0,
                        'updated_by' => auth()->id(),
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'All users set to ABSENT for ' . $request->date
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function importProgress($log)
    {
        $importLog = AttendanceImportLog::findOrFail($log);

        return response()->json([
            'status' => $importLog->status,
            'processed_rows' => $importLog->processed_rows,
            'total_rows' => $importLog->total_rows,
            'imported_records' => $importLog->imported_records,
            'created_users' => $importLog->created_users,
            'message' => $importLog->message,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
            'region' => 'required|in:north,south',
        ]);

        $file = $request->file('file');
        $hash = md5_file($file->getRealPath());

        $existing = AttendanceImportLog::where('file_hash', $hash)->first();

        if ($existing) {
            if ($existing->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'This file was already imported before.',
                    'log_id' => $existing->id,
                ], 422);
            }

            $existing->update([
                'file_name' => $file->getClientOriginalName(),
                'imported_by' => auth()->id(),
                'status' => 'processing',
                'message' => 'Import restarted',
                'processed_rows' => 0,
                'imported_records' => 0,
                'created_users' => 0,
                'total_rows' => 0,
            ]);

            $log = $existing;
        } else {
            $log = AttendanceImportLog::create([
                'file_hash' => $hash,
                'file_name' => $file->getClientOriginalName(),
                'imported_by' => auth()->id(),
                'status' => 'processing',
                'message' => 'Import started',
                'processed_rows' => 0,
                'imported_records' => 0,
                'created_users' => 0,
                'total_rows' => 0,
            ]);
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $sheetNames = $spreadsheet->getSheetNames();

            foreach ($sheetNames as $sheetName) {
                \Maatwebsite\Excel\Facades\Excel::import(
                    new \App\Imports\AttendanceSheetImport(
                        $log,
                        $sheetName,
                        auth()->id(),
                        $request->region
                    ),
                    $file
                );
            }

            $log->refresh();
            $log->update([
                'status' => 'completed',
                'message' => 'Import completed successfully',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attendance imported successfully',
                'log_id' => $log->id,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'log_id' => $log->id,
            ], 500);
        }
    }

    public function dashboard(Request $request)
    {
        $month = $request->month ?? now()->format('m');
        $year = $request->year ?? now()->format('Y');
        $today = now()->toDateString();

        $totalUsers = User::count();

        $todayPresent = Attendance::whereDate('date', $today)
            ->where('status', 1)
            ->count();

        $todayAbsent = Attendance::whereDate('date', $today)
            ->where('status', 0)
            ->count();

        $attendanceRate = $totalUsers > 0
            ? round(($todayPresent / $totalUsers) * 100, 2)
            : 0;

        $monthPresent = Attendance::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('status', 1)
            ->count();

        $monthAbsent = Attendance::whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('status', 0)
            ->count();

        $topEmployees = User::withCount([
            'attendances as total_present' => function ($q) use ($month, $year) {
                $q->whereYear('date', $year)
                    ->whereMonth('date', $month)
                    ->where('status', 1);
            }
        ])
            ->orderByDesc('total_present')
            ->limit(10)
            ->get();

        $lowEmployees = User::withCount([
            'attendances as total_present' => function ($q) use ($month, $year) {
                $q->whereYear('date', $year)
                    ->whereMonth('date', $month)
                    ->where('status', 1);
            }
        ])
            ->orderBy('total_present')
            ->limit(10)
            ->get();

        $monthlySummary = Attendance::selectRaw('DAY(date) as day_number')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as present_count')
            ->selectRaw('SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as absent_count')
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->groupByRaw('DAY(date)')
            ->orderByRaw('DAY(date)')
            ->get();

        $contractStats = User::select('contract_type')
            ->selectRaw("
                COUNT(attendances.id) as total_records,
                SUM(CASE WHEN attendances.status = 1 THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN attendances.status = 0 THEN 1 ELSE 0 END) as absent_count
            ")
            ->leftJoin('attendances', function ($join) use ($month, $year) {
                $join->on('users.id', '=', 'attendances.user_id')
                    ->whereMonth('attendances.date', $month)
                    ->whereYear('attendances.date', $year);
            })
            ->groupBy('contract_type')
            ->get()
            ->map(function ($item) {
                $total = ($item->present_count ?? 0) + ($item->absent_count ?? 0);

                $item->attendance_rate = $total > 0
                    ? round((($item->present_count ?? 0) / $total) * 100, 2)
                    : 0;

                return $item;
            });

        $roleStats = Role::select('roles.name')
            ->selectRaw("
                COUNT(attendances.id) as total_records,
                SUM(CASE WHEN attendances.status = 1 THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN attendances.status = 0 THEN 1 ELSE 0 END) as absent_count
            ")
            ->leftJoin('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->leftJoin('users', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->leftJoin('attendances', function ($join) use ($month, $year) {
                $join->on('users.id', '=', 'attendances.user_id')
                    ->whereMonth('attendances.date', $month)
                    ->whereYear('attendances.date', $year);
            })
            ->groupBy('roles.name')
            ->get()
            ->map(function ($item) {
                $total = ($item->present_count ?? 0) + ($item->absent_count ?? 0);

                $item->attendance_rate = $total > 0
                    ? round((($item->present_count ?? 0) / $total) * 100, 2)
                    : 0;

                return $item;
            });

        $dailyChartCategories = $monthlySummary->pluck('day_number')
            ->map(fn($d) => 'Day ' . $d)
            ->values()
            ->toArray();

        $dailyPresentSeries = $monthlySummary->pluck('present_count')
            ->map(fn($v) => (int) $v)
            ->values()
            ->toArray();

        $dailyAbsentSeries = $monthlySummary->pluck('absent_count')
            ->map(fn($v) => (int) $v)
            ->values()
            ->toArray();

        $contractChartCategories = $contractStats->pluck('contract_type')
            ->map(fn($v) => $v ?: 'N/A')
            ->values()
            ->toArray();

        $contractChartSeries = $contractStats->pluck('attendance_rate')
            ->map(fn($v) => (float) $v)
            ->values()
            ->toArray();

        $roleChartCategories = $roleStats->pluck('name')
            ->values()
            ->toArray();

        $roleChartSeries = $roleStats->pluck('attendance_rate')
            ->map(fn($v) => (float) $v)
            ->values()
            ->toArray();

        return view('Attendance.attendance_dashboard', compact(
            'month',
            'year',
            'totalUsers',
            'todayPresent',
            'todayAbsent',
            'attendanceRate',
            'monthPresent',
            'monthAbsent',
            'topEmployees',
            'lowEmployees',
            'monthlySummary',
            'contractStats',
            'roleStats',
            'dailyChartCategories',
            'dailyPresentSeries',
            'dailyAbsentSeries',
            'contractChartCategories',
            'contractChartSeries',
            'roleChartCategories',
            'roleChartSeries'
        ));
    }

    public function exportMonthlyReport(Request $request)
    {
        $month = $request->month ?? now()->format('m');
        $year = $request->year ?? now()->format('Y');

        return Excel::download(
            new AttendanceMonthlyReportExport($month, $year),
            "attendance_report_{$year}_{$month}.xlsx"
        );
    }


    public function monthlyReport(Request $request)
    {
        $month = $request->month ?? now()->format('m');
        $year = $request->year ?? now()->format('Y');

        $users = \App\Models\User::with('roles')
            ->withCount([
                'attendances as total_present' => function ($q) use ($month, $year) {
                    $q->whereYear('date', $year)
                        ->whereMonth('date', $month)
                        ->where('status', 1);
                },
                'attendances as total_absent' => function ($q) use ($month, $year) {
                    $q->whereYear('date', $year)
                        ->whereMonth('date', $month)
                        ->where('status', 0);
                }
            ])
            ->get()
            ->map(function ($user) {
                $totalDays = $user->total_present + $user->total_absent;
                $rate = $totalDays > 0 ? round(($user->total_present / $totalDays) * 100, 2) : 0;

                return [
                    'name' => $user->name_en ?? $user->name,
                    'id_no' => $user->id_no,
                    'phone' => $user->phone,
                    'contract_type' => $user->contract_type,
                    'role' => optional($user->roles->first())->name,
                    'present_days' => $user->total_present,
                    'absent_days' => $user->total_absent,
                    'attendance_rate' => $rate . '%',
                ];
            });

        return response()->json([
            'data' => $users
        ]);
    }



}