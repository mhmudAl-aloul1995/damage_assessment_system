<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class LoginLogController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->select('id', 'name', 'email', 'username')
            ->orderBy('name')
            ->get();

        return view('login_logs.index', compact('users'));
    }

    public function data(Request $request)
    {
        $query = LoginLog::query()
            ->with('user')
            ->latest('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('is_success', $request->status === 'success');
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'like', '%' . $request->ip_address . '%');
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()

            ->addColumn('user_name', function ($row) {
                return $row->user?->name ?? $row->name ?? '-';
            })

            ->addColumn('status_badge', function ($row) {
                if ($row->is_success) {
                    return '<span class="badge badge-light-success">Success</span>';
                }

                return '<span class="badge badge-light-danger">Failed</span>';
            })

            ->addColumn('login_at', function ($row) {
                return $row->logged_in_at
                    ? $row->logged_in_at->format('Y-m-d H:i:s')
                    : '-';
            })

            ->addColumn('logout_at', function ($row) {
                return $row->logged_out_at
                    ? $row->logged_out_at->format('Y-m-d H:i:s')
                    : '-';
            })

            ->addColumn('duration', function ($row) {
                if (! $row->logged_in_at || ! $row->logged_out_at) {
                    return '-';
                }

                return $row->logged_in_at->diffForHumans($row->logged_out_at, true);
            })

            ->addColumn('browser', function ($row) {
                return $row->user_agent
                    ? '<span title="' . e($row->user_agent) . '">' . e(str($row->user_agent)->limit(60)) . '</span>'
                    : '-';
            })

            ->setRowClass(function ($row) {
                return $row->is_success ? '' : 'table-danger';
            })

            ->rawColumns(['status_badge', 'browser'])
            ->make(true);
    }
}