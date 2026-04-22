<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = config('app.supported_locales', ['en']);
        $locale = $this->resolveLocale($request->user(), $request);

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = config('app.fallback_locale');
        }

        App::setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(?Authenticatable $user, Request $request): string
    {
        $preferredLocale = $user?->preferred_locale;

        if (is_string($preferredLocale) && $preferredLocale !== '') {
            return $preferredLocale;
        }

        $sessionLocale = $request->session()->get('locale');

        if (is_string($sessionLocale) && $sessionLocale !== '') {
            return $sessionLocale;
        }

        $cookieLocale = $request->cookie('preferred_locale');

        if (is_string($cookieLocale) && $cookieLocale !== '') {
            return $cookieLocale;
        }

        return config('app.locale');
    }
}
