<?php

namespace App\Http\Controllers\DamageAssessment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Rap2hpoutre\FastExcel\FastExcel;
use Yajra\Datatables\Enginges\EloquentEngine;
class AttendanceController extends Controller
{
    /**
     * عرض الصفحة
     */
    public function index()
    {
        return view('DamageAssessment.attendance');
    }

    /**
     * DataTable Data
     */
    public function data(Request $request)
    {
        $month = $request->month ?: now()->month;
        $year  = $request->year ?: now()->year;

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        $users = User::with([
            'attendances' => function ($q) use ($month, $year) {
                $q->whereMonth('date', $month)
                  ->whereYear('date', $year);
            }
        ])->get();

        $rows = $users->map(function ($user, $index) use ($year, $month, $daysInMonth) {
            $row = [
                'DT_RowIndex'   => $index + 1,
                'contract_date' => $user->contract_start_date,
                'name_en'       => $user->name,
                'name_ar'       => $user->name_ar,
                'position'      => $user->position,
                'id_no'         => $user->id_number,
                'contact'       => $user->phone,
                'total'         => 0,
            ];

            for ($day = 1; $day <= 31; $day++) {
                $row['day_' . $day] = '';
            }

            $total = 0;

            foreach (range(1, $daysInMonth) as $day) {
                $date = Carbon::createFromDate($year, $month, $day)->format('Y-m-d');

                $attendance = $user->attendances->firstWhere('date', $date);

                if ($attendance) {
                    $status = (int) $attendance->status;
                    $row['day_' . $day] = $status;
                    $total += $status;
                }
            }

            $row['total'] = $total;

            return $row;
        });

        return DataTables::of($rows)->make(true);
    }

    /**
     * حفظ أو تحديث الحضور (click 1/0)
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date'    => 'required|date',
            'status'  => 'required|in:0,1'
        ]);

        Attendance::updateOrCreate(
            [
                'user_id' => $request->user_id,
                'date'    => $request->date,
            ],
            [
                'status' => $request->status,
                'notes'  => $request->notes ?? null
            ]
        );

        return response()->json([
            'message' => 'Attendance saved successfully'
        ]);
    }

    /**
     * حذف الحضور (اختياري)
     */
    public function delete(Request $request)
    {
        Attendance::where('user_id', $request->user_id)
            ->where('date', $request->date)
            ->delete();

        return response()->json([
            'message' => 'Deleted'
        ]);
    }
}