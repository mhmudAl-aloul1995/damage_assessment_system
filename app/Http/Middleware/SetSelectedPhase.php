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

            if ($phase === null && $phaseContext->canSelectAll()) {
                $request->session()->put(PhaseContext::SESSION_KEY, $phaseContext->allSessionValue());
            } elseif ($phaseContext->isAllowed($phase)) {
                $request->session()->put(PhaseContext::SESSION_KEY, $phase);
            } else {
                $fallbackPhase = $phaseContext->fallbackSelected();

                if ($fallbackPhase === null) {
                    $request->session()->put(PhaseContext::SESSION_KEY, $phaseContext->allSessionValue());
                } else {
                    $request->session()->put(PhaseContext::SESSION_KEY, $fallbackPhase);
                }
            }
        }

        View::share('phaseOptions', $phaseContext->options());
        View::share('selectedPhaseNumber', $phaseContext->selected());
        View::share('selectedPhaseLabel', $phaseContext->label($phaseContext->selected()));
        View::share('canSelectAllPhases', $phaseContext->canSelectAll());

        return $next($request);
    }
}
