<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, config('app.supported_locales', ['en']), true), 404);

        $request->session()->put('locale', $locale);
        Cookie::queue(Cookie::forever('preferred_locale', $locale));

        if ($request->user() !== null && $request->user()->preferred_locale !== $locale) {
            $request->user()->forceFill([
                'preferred_locale' => $locale,
            ])->save();
        }

        return redirect()->back();
    }
}
