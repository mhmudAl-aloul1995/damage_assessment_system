<?php

namespace App\Http\Controllers\DamageAssessment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index()
    {
        return view('DamageAssessment.attendance');
    }

   public function data(Request $request)
{
    $month = $request->month ?? date('m');
    $year  = $request->year ?? date('Y');

    $dateContext = Carbon::createFromDate($year, $month, 1);
    $daysInMonth = $dateContext->daysInMonth;

    $users = User::with([
            'roles',
            'attendances' => function ($query) use ($month, $year) {
                $query->whereMonth('date', $month)
                      ->whereYear('date', $year);
            }
        ])
        ->withCount([
            'attendances as total_present' => function ($query) use ($month, $year) {
                $query->whereMonth('date', $month)
                      ->whereYear('date', $year)
                      ->where('status', 1);
            }
        ]);

    // ✅ Filter by role
    if ($request->filled('role')) {
        $users->whereHas('roles', function ($q) use ($request) {
            $q->where('name', $request->role);
        });
    }

    // ✅ Filter by contract type
    if ($request->filled('contract_type')) {
        $users->where('contract_type', $request->contract_type);
    }

    $dataTable = datatables()->eloquent($users)
        ->addIndexColumn()
        ->addColumn('id', function ($user) {
            return $user->id;
        })
        ->addColumn('name', function ($user) {
            return $user->name ?? '-';
        })
        ->addColumn('name_en', function ($user) {
            return $user->name_en ?? '-';
        })
        ->addColumn('id_no', function ($user) {
            return $user->id_no ?? '-';
        })
        ->addColumn('phone', function ($user) {
            return $user->phone ?? '-';
        })
        ->addColumn('role', function ($user) {
            return optional($user->roles->first())->name ?? '-';
        })
        ->addColumn('contract_type', function ($user) {
            return $user->contract_type ?? '-';
        })
        ->addColumn('total', function ($user) {
            return $user->total_present ?? 0;
        });

    for ($i = 1; $i <= 31; $i++) {
        $dataTable->addColumn("day_$i", function ($user) use ($i, $daysInMonth, $year, $month) {
            if ($i > $daysInMonth) {
                return 'N/A';
            }

            $dateStr = Carbon::createFromDate($year, $month, $i)->format('Y-m-d');

            $record = $user->attendances->first(function ($attendance) use ($dateStr) {
                return Carbon::parse($attendance->date)->format('Y-m-d') === $dateStr;
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
}