<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\StartAccessPreviewRequest;
use App\Models\User;
use App\Support\Access\AccessPreview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AccessPreviewController extends Controller
{
    public function __construct(private AccessPreview $preview) {}

    public function create(Request $request): View
    {
        abort_unless($this->preview->canStart($request->user(), $request), 403);

        return view('UserManagement.access_preview', [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email', 'is_active']),
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
            'selectedUserId' => $request->integer('user_id', $request->user()->id),
        ]);
    }

    public function store(StartAccessPreviewRequest $request): RedirectResponse
    {
        $target = User::query()->where('is_active', true)->findOrFail($request->validated('user_id'));
        $role = $request->validated('mode') === 'role'
            ? Role::query()->where('guard_name', 'web')->findOrFail($request->validated('role_id'))
            : null;

        $this->preview->start($request, $target, $role);

        return redirect()->route('access-preview.show');
    }

    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->attributes->has(AccessPreview::SESSION_KEY)) {
            return redirect()->route('access-preview.create');
        }

        return view('UserManagement.access_preview_active');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->preview->stop($request, $request->user());

        return redirect()->route('users.index');
    }
}
