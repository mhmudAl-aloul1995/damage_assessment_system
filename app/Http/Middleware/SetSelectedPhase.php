<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Phase\PhaseContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetSelectedPhase
{
    public function handle(Request $request, Closure $next): Response
    {
        $phaseContext = app(PhaseContext::class);

        if ($request->has(PhaseContext::REQUEST_KEY) && $request->hasSession()) {
            $phase = $phaseContext->normalize($request->query(PhaseContext::REQUEST_KEY));

            if ($phase === null) {
                $request->session()->forget(PhaseContext::SESSION_KEY);
            } else {
                $request->session()->put(PhaseContext::SESSION_KEY, $phase);
            }
        }

        View::share('phaseOptions', $phaseContext->options());
        View::share('selectedPhaseNumber', $phaseContext->selected());
        View::share('selectedPhaseLabel', $phaseContext->label($phaseContext->selected()));

        return $next($request);
    }
}
