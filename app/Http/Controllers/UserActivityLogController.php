<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class UserActivityLogController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return view('UserManagement.activity_logs', compact('users'));
    }

    public function data(Request $request): JsonResponse
    {
        $query = UserActivityLog::query()
            ->with('user')
            ->latest('occurred_at')
            ->latest('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('action_type')) {
            $query->where('action_type', (string) $request->string('action_type'));
        }

        if ($request->filled('method')) {
            $query->where('method', (string) $request->string('method'));
        }

        if ($request->filled('url')) {
            $query->where('url', 'like', '%'.(string) $request->string('url').'%');
        }

        if ($request->filled('from_date')) {
            $query->whereDate('occurred_at', '>=', $request->date('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('occurred_at', '<=', $request->date('to_date'));
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('user_name', fn (UserActivityLog $log): string => $log->user?->name ?? $log->user_name ?? '-')
            ->addColumn('action_badge', function (UserActivityLog $log): string {
                if ($log->action_type === 'page_visit') {
                    return '<span class="badge badge-light-primary">Page Visit</span>';
                }

                return '<span class="badge badge-light-warning">Action</span>';
            })
            ->addColumn('method_badge', fn (UserActivityLog $log): string => '<span class="badge badge-light">'.e($log->method).'</span>')
            ->addColumn('url_label', fn (UserActivityLog $log): string => '<span title="'.e($log->url).'">'.e(str($log->url)->limit(90)).'</span>')
            ->addColumn('browser', fn (UserActivityLog $log): string => $log->user_agent
                ? '<span title="'.e($log->user_agent).'">'.e(str($log->user_agent)->limit(60)).'</span>'
                : '-')
            ->addColumn('occurred_at_formatted', fn (UserActivityLog $log): string => $log->occurred_at?->format('Y-m-d H:i:s') ?? '-')
            ->rawColumns(['action_badge', 'method_badge', 'url_label', 'browser'])
            ->make(true);
    }
}
