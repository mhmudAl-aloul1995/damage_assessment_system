<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\DamageAssessment\AuditReviewerAssignmentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Spatie\Permission\Models\Role;

class AuditReviewerController extends Controller
{
    private const ROLE_NAME = 'Audit Reviewer';

    public function __construct()
    {
        $this->middleware('role:Auditing Supervisor|Database Officer');
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('audit.index', [
            'audit_reviewers' => 1,
        ]);
    }

    public function store(AuditReviewerAssignmentRequest $request): RedirectResponse
    {
        $this->ensureRoleExists();

        User::query()
            ->findOrFail($request->integer('user_id'))
            ->assignRole(self::ROLE_NAME);

        return back()->with('success', 'تمت إضافة صلاحية Audit Reviewer للمستخدم.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->removeRole(self::ROLE_NAME);

        return back()->with('success', 'تمت إزالة صلاحية Audit Reviewer من المستخدم.');
    }

    private function ensureRoleExists(): void
    {
        Role::findOrCreate(self::ROLE_NAME, 'web');
    }
}
