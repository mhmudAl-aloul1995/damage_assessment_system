<?php

namespace App\Modules\HR\Http\Middleware;

use App\Modules\HR\Models\AuditLog;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditNativeAttendanceFailure
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
            if ($response->getStatusCode() >= 400) {
                $this->record($request, $response->getStatusCode());
            }

            return $response;
        } catch (Throwable $exception) {
            $status = match (true) {
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                $exception instanceof ValidationException => 422,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => $exception->status() ?? 403,
                $exception instanceof ModelNotFoundException => 404,
                default => 500,
            };
            $this->record($request, $status);
            throw $exception;
        }
    }

    private function record(Request $request, int $status): void
    {
        try {
            if (! Schema::hasTable('hr_audit_logs')) {
                return;
            }
            $actorId = $request->user()?->id;
            $route = $request->route()?->getName() ?? 'native_hr_api';
            $key = 'hr-denial:'.hash('sha256', ($actorId ?? $request->ip()).'|'.$route.'|'.$status);
            if (! Cache::add($key, true, 60)) {
                return;
            }
            AuditLog::query()->create([
                'actor_id' => $actorId, 'action' => 'attendance.native_request_denied', 'subject_type' => 'hr_native_api',
                'metadata' => ['route' => $route, 'status' => $status, 'sample_window_seconds' => 60],
                'created_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            report(new \RuntimeException('Unable to persist an HR API security audit.'));
        }
    }
}
