<?php

declare(strict_types=1);

namespace App\Http\Controllers\Committee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Committee\StoreCommitteeMemberRequest;
use App\Http\Requests\Committee\UpdateCommitteeMemberRequest;
use App\Models\CommitteeMember;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Yajra\DataTables\Facades\DataTables;

class CommitteeMemberController extends Controller
{
    public function __construct()
    {
       /*  $this->middleware('permission:manage committee members'); */
    }

    public function index(): View
    {
        return view('Committee.Members.index', [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'phone']),
        ]);
    }

    public function data(): JsonResponse
    {
        $members = CommitteeMember::query()->with('user')->select('committee_members.*');

        return DataTables::eloquent($members)
            ->editColumn('name', fn (CommitteeMember $member): string => e($member->name))
            ->editColumn('user_id', fn (CommitteeMember $member): string => e($member->user?->name ?? '-'))
            ->editColumn('is_active', fn (CommitteeMember $member): string => $member->is_active
                ? '<span class="badge badge-light-success">مفعل</span>'
                : '<span class="badge badge-light-danger">معطل</span>')
            ->editColumn('is_required', fn (CommitteeMember $member): string => $member->is_required
                ? '<span class="badge badge-light-primary">مطلوب</span>'
                : '<span class="badge badge-light-secondary">اختياري</span>')
            ->addColumn('actions', function (CommitteeMember $member): string {
                return '
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light-primary btn-sm committee-member-edit"
                            data-member=\''.json_encode([
                    'id' => $member->id,
                    'name' => $member->name,
                    'phone' => $member->phone,
                    'title' => $member->title,
                    'user_id' => $member->user_id,
                    'is_active' => $member->is_active,
                    'is_required' => $member->is_required,
                    'sort_order' => $member->sort_order,
                    'signature_path' => $member->signature_path,
                ], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT).'\'>تعديل</button>
                        <form method="POST" action="'.route('committee-members.destroy', $member).'" class="d-inline">
                            '.csrf_field().method_field('DELETE').'
                            <button type="submit" class="btn btn-light-danger btn-sm" onclick="return confirm(\'هل تريد حذف العضو؟\')">حذف</button>
                        </form>
                    </div>
                ';
            })
            ->rawColumns(['is_active', 'is_required', 'actions'])
            ->toJson();
    }

    public function store(StoreCommitteeMemberRequest $request): RedirectResponse
    {
        CommitteeMember::query()->create($this->payload($request->validated()));

        return redirect()
            ->route('committee-members.index')
            ->with('success', 'تم إضافة عضو اللجنة بنجاح.');
    }

    public function update(UpdateCommitteeMemberRequest $request, CommitteeMember $committeeMember): RedirectResponse
    {
        $committeeMember->update($this->payload($request->validated()));

        return redirect()
            ->route('committee-members.index')
            ->with('success', 'تم تحديث عضو اللجنة بنجاح.');
    }

    public function destroy(CommitteeMember $committeeMember): RedirectResponse
    {
        $committeeMember->delete();

        return redirect()
            ->route('committee-members.index')
            ->with('success', 'تم حذف عضو اللجنة.');
    }

    private function payload(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'title' => $validated['title'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'signature_path' => $validated['signature_path'] ?? null,
        ];
    }
}
