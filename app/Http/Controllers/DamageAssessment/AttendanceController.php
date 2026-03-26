<?php

namespace App\Http\Controllers\DamageAssessment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

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
        $month = $request->month ?? now()->month;
        $year  = $request->year ?? now()->year;

        $daysInMonth = Carbon::create($year, $month)->daysInMonth;

        $users = User::with(['attendances' => function ($q) use ($month, $year) {
            $q->whereMonth('date', $month)
              ->whereYear('date', $year);
        }]);

        return datatables()->of($users)
            ->addIndexColumn()

            ->addColumn('contract_date', fn($u) => $u->contract_start_date)
            ->addColumn('name_en', fn($u) => $u->name)
            ->addColumn('name_ar', fn($u) => $u->name_ar)
            ->addColumn('position', fn($u) => $u->position)
            ->addColumn('id_no', fn($u) => $u->id_number)
            ->addColumn('contact', fn($u) => $u->phone)

            /**
             * مجموع الحضور
             */
            ->addColumn('total', function ($u) {
                return $u->attendances->sum('status');
            })

            /**
             * أيام الشهر (1 → 31)
             */
            ->addColumn('days', function ($u) use ($year, $month, $daysInMonth) {

                $days = [];

                foreach (range(1, $daysInMonth) as $day) {

                    $date = Carbon::create($year, $month, $day)->format('Y-m-d');

                    $record = $u->attendances->firstWhere('date', $date);

                    $days[$day] = $record ? $record->status : '';
                }

                return $days;
            })

            /**
             * HTML rendering للأيام
             */
            ->addColumn('days_html', function ($u) use ($year, $month, $daysInMonth) {

                $html = '';

                foreach (range(1, $daysInMonth) as $day) {

                    $date = Carbon::create($year, $month, $day)->format('Y-m-d');

                    $record = $u->attendances->firstWhere('date', $date);
                    $status = $record ? $record->status : '';

                    // لون مثل Excel
                    $color = '';
                    if ($status === 1) {
                        $color = 'background-color:#d4edda'; // أخضر
                    } elseif ($status === 0) {
                        $color = 'background-color:#f8d7da'; // أحمر
                    }

                    $html .= "<td style='{$color}'>{$status}</td>";
                }

                return $html;
            })

            ->rawColumns(['days_html'])
            ->make(true);
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