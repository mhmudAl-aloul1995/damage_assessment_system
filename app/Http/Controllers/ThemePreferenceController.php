<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateThemePreferenceRequest;
use App\Support\Navigation\Sidebar;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ThemePreferenceController extends Controller
{
    public function update(UpdateThemePreferenceRequest $request): RedirectResponse
    {
        $isEnabled = $request->boolean('enabled');

        $request->session()->put('ui.metronic9_enabled', $isEnabled);

        if (! $isEnabled && str_contains((string) $request->headers->get('referer'), route('theme.metronic9.preview', [], false))) {
            return redirect()->to(app_route('dashboard'));
        }

        return redirect()->back();
    }

    public function preview(): View
    {
        return view('theme.metronic9-demo3', [
            'sidebarModules' => auth()->user() !== null
                ? Sidebar::forUser(auth()->user())
                : collect(),
        ]);
    }
}
