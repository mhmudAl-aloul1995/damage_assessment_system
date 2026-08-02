<?php

namespace App\Modules\DamageAssessment\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Modules\DamageAssessment\AuditReviewerAssignmentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewResponse;
use Spatie\Permission\Models\Role;

class AuditReviewerController extends Controller
{
    private const ROLE_NAME = 'Audit Reviewer';

    public function __construct()
    {
        $this->middleware('role:Auditing Supervisor|Database Officer');
    }

    public function index(): ViewResponse
    {
        $this->ensureRoleExists();

        return View::make('damage-assessment::audit.auditReviewers', [
            'reviewers' => User::role(self::ROLE_NAME)
                ->orderBy('name')
                ->get(),
            'users' => User::query()
                ->with('roles')
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', self::ROLE_NAME))
                ->orderBy('name')
                ->get(),
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
