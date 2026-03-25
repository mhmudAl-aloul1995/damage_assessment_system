<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
           $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->hasRole('system manager')) {
            return redirect()->route('dashboard');
        }

        if ($user->hasRole('area manager')) {
            return redirect()->route('dashboard');
        }

        if ($user->hasRole('team leader')) {
            return redirect()->route('dashboard');
        }

        if ($user->hasRole('Engineering Auditor')) {
            return redirect()->route('auditBuilding');
        }

        if ($user->hasRole('Legal Auditor')) {
            return redirect()->route('auditBuilding');
        }

        if ($user->hasRole('field engineer')) {
            return redirect()->route('engineer.tasks');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
