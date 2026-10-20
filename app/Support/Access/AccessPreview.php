<?php

namespace App\Support\Access;

use App\Models\User;
use App\Models\UserActivityLog;
use App\Support\Phase\PhaseContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AccessPreview
{
    public const SESSION_KEY = 'access_preview';

    public function canStart(?User $user, Request $request): bool
    {
        return $user?->is_active && $user->hasRole('Database Officer')
            && ! $request->session()->has(self::SESSION_KEY);
    }

    public function start(Request $request, User $target, ?Role $role): void
    {
        $actor = $request->user();
        abort_unless($this->canStart($actor, $request), 403);

        $preview = [
            'id' => (string) Str::uuid(),
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'user_id' => $target->id,
            'user_name' => $target->name,
            'role_id' => $role?->id,
            'role_name' => $role?->name,
            'mode' => $role ? 'role' : 'user',
            'original_phase' => $request->session()->get(PhaseContext::SESSION_KEY),
        ];

        $this->record($request, $actor, $preview, 'started');
        $request->session()->put(self::SESSION_KEY, $preview);
        $request->session()->forget([PhaseContext::SESSION_KEY, 'url.intended', 'auth.password_confirmed_at']);
        $request->session()->regenerate(true);
    }

    public function stop(Request $request, ?User $actor, string $reason = 'stopped'): void
    {
        $preview = $request->session()->get(self::SESSION_KEY);

        if (! is_array($preview)) {
            return;
        }

        $this->record($request, $actor, $preview, $reason);
        $request->session()->forget([self::SESSION_KEY, PhaseContext::SESSION_KEY, 'url.intended', 'auth.password_confirmed_at']);

        if ($preview['original_phase'] !== null) {
            $request->session()->put(PhaseContext::SESSION_KEY, $preview['original_phase']);
        }

        $request->session()->regenerate(true);
    }

    /** @param array<string, mixed> $preview */
    public function context(array $preview): array
    {
        return array_diff_key($preview, ['original_phase' => true]);
    }

    /** @param array<string, mixed> $preview */
    private function record(Request $request, ?User $actor, array $preview, string $event): void
    {
        UserActivityLog::query()->create([
            'user_id' => $actor?->id,
            'user_name' => $preview['actor_name'],
            'user_email' => $actor?->email,
            'action_type' => 'action',
            'method' => $request->method(),
            'url' => '/'.ltrim($request->path(), '/'),
            'route_name' => $request->route()?->getName(),
            'description' => Str::limit('Access preview '.$event.': '.$preview['user_name'].($preview['role_name'] ? ' / '.$preview['role_name'] : ''), 250),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status_code' => 302,
            'metadata' => ['access_preview' => $this->context($preview), 'event' => $event],
            'occurred_at' => now(),
        ]);
    }
}
