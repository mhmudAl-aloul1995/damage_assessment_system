<?php

namespace App\Http\Controllers;

use App\Models\SystemOperationLog;
use Yajra\DataTables\Facades\DataTables;

class SystemLogController extends Controller
{
    public function index()
    {
        return view('admin.system_logs');
    }

    public function data()
    {
        $query = SystemOperationLog::query()->latest();

        return DataTables::of($query)

            ->editColumn('status', function ($row) {

                if ($row->status == 'success') {
                    return '<span class="badge badge-light-success">Success</span>';
                }

                if ($row->status == 'failed') {
                    return '<span class="badge badge-light-danger">Failed</span>';
                }

                return '<span class="badge badge-light-warning">Running</span>';
            })

            ->editColumn('duration_seconds', function ($row) {
                return ($row->duration_seconds ?? 0) . ' s';
            })

            ->editColumn('records_per_second', function ($row) {
                return ($row->records_per_second ?? 0) . '/s';
            })

            ->editColumn('finished_at', function ($row) {
                return optional($row->finished_at)->format('Y-m-d H:i:s');
            })

            ->rawColumns(['status'])

            ->make(true);
    }
}