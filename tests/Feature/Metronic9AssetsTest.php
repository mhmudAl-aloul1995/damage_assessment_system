<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

test('metronic 9 assets are available without replacing the current metronic 8 bundle', function () {
    $publicPath = public_path('assets/metronic9');

    expect(File::exists($publicPath.'/css/styles.css'))->toBeTrue()
        ->and(File::exists($publicPath.'/css/phc-theme-overrides.css'))->toBeTrue()
        ->and(File::exists($publicPath.'/js/core.bundle.js'))->toBeTrue()
        ->and(File::exists($publicPath.'/vendors/ktui/ktui.min.js'))->toBeTrue()
        ->and(File::exists($publicPath.'/vendors/keenicons/styles.bundle.css'))->toBeTrue()
        ->and(File::exists(public_path('assets/css/style.bundle.css')))->toBeTrue()
        ->and(File::exists(public_path('assets/js/scripts.bundle.js')))->toBeTrue();
});

test('metronic 9 partials point to the isolated asset path', function () {
    $styles = File::get(resource_path('views/layouts/partials/metronic9-styles.blade.php'));
    $scripts = File::get(resource_path('views/layouts/partials/metronic9-scripts.blade.php'));

    expect($styles)->toContain("asset('assets/metronic9/css/phc-theme-overrides.css')")
        ->and($styles)->not->toContain("asset('assets/metronic9/css/styles.css')")
        ->and($scripts)->toContain("asset('assets/metronic9/js/core.bundle.js')")
        ->and($scripts)->toContain("asset('assets/metronic9/vendors/ktui/ktui.min.js')");
});

test('users can switch the metronic 9 mode on the current application pages', function () {
    $this
        ->from('/dashboard')
        ->post(route('theme.metronic9.update'), ['enabled' => '1'])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('ui.metronic9_enabled', true);

    $this
        ->from('/dashboard')
        ->post(route('theme.metronic9.update'), ['enabled' => '0'])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('ui.metronic9_enabled', false);
});

test('the application layout exposes the metronic 9 switch and loads only the safe application skin', function () {
    $layout = File::get(resource_path('views/layouts/app.blade.php'));

    expect(Route::has('theme.metronic9.update'))->toBeTrue()
        ->and(Route::has('theme.metronic9.preview'))->toBeTrue()
        ->and($layout)->toContain("route('theme.metronic9.update')")
        ->and($layout)->toContain('name="enabled" value="1"')
        ->and($layout)->toContain('phc-metronic9-enabled')
        ->and($layout)->toContain("@include('layouts.partials.metronic9-styles')")
        ->and($layout)->not->toContain('phc-m9-modules-sidebar')
        ->and($layout)->not->toContain('All Modules')
        ->and($layout)->not->toContain("@include('layouts.partials.metronic9-scripts')");
});

test('metronic 9 overrides protect the current layout without replacing the sidebar', function () {
    $overrides = File::get(public_path('assets/metronic9/css/phc-theme-overrides.css'));

    expect($overrides)->toContain('.phc-metronic9-enabled .card')
        ->and($overrides)->toContain('.phc-metronic9-enabled .btn-primary')
        ->and($overrides)->toContain('.phc-metronic9-enabled .table')
        ->and($overrides)->toContain('.phc-metronic9-enabled .form-control:focus')
        ->and($overrides)->toContain('.phc-metronic9-enabled.app-blank')
        ->and($overrides)->toContain('.phc-metronic9-enabled #kt_sign_in_submit')
        ->and($overrides)->toContain('.phc-metronic9-enabled #kt_app_sidebar_menu')
        ->and($overrides)->toContain('.phc-metronic9-enabled #kt_app_sidebar')
        ->and($overrides)->toContain('min-width: 0')
        ->and($overrides)->not->toContain('max-width: none')
        ->and($overrides)->not->toContain('phc-m9-modules-sidebar')
        ->and($overrides)->not->toContain('--phc-m9-sidebar-width')
        ->and($overrides)->not->toContain('margin-left: var(--phc-m9-sidebar-width)')
        ->and($overrides)->not->toContain('left: var(--phc-m9-sidebar-width)')
        ->and($overrides)->toContain('#3e97ff');
});

test('the login screen exposes the metronic 9 switch before authentication without injecting preview assets', function () {
    $login = File::get(resource_path('views/auth/login.blade.php'));

    expect($login)->toContain("route('theme.metronic9.update')")
        ->and($login)->toContain('name="enabled" value="1"')
        ->and($login)->not->toContain('phc-metronic9-enabled')
        ->and($login)->not->toContain("@include('layouts.partials.metronic9-styles')")
        ->and($login)->not->toContain("@include('layouts.partials.metronic9-scripts')");
});

test('the isolated metronic 9 preview uses the full metronic 9 stylesheet safely', function () {
    $preview = File::get(resource_path('views/theme/metronic9-demo3.blade.php'));

    expect($preview)->toContain("asset('assets/metronic9/css/styles.css')")
        ->and($preview)->toContain("asset('assets/metronic9/vendors/ktui/ktui.min.js')")
        ->and($preview)->toContain("route('theme.metronic9.update')")
        ->and($preview)->toContain('Metronic 9 Demo3')
        ->and($preview)->toContain('id="sidebar"')
        ->and($preview)->toContain('data-kt-drawer-toggle="#sidebar"')
        ->and($preview)->toContain("@include('theme.partials.metronic9-phc-sidebar')")
        ->and($preview)->not->toContain('All Modules')
        ->and($preview)->not->toContain('phc-m9-modules-sidebar')
        ->and($preview)->not->toContain('phc-m9-demo-shell');
});

test('the metronic 9 sidebar renders the current application navigation inside the native rail', function () {
    $partial = File::get(resource_path('views/theme/partials/metronic9-phc-sidebar.blade.php'));
    $controller = File::get(app_path('Http/Controllers/ThemePreferenceController.php'));

    expect($controller)->toContain('Sidebar::forUser')
        ->and($controller)->toContain("'sidebarModules'")
        ->and($partial)->toContain('$sidebarModules')
        ->and($partial)->toContain('kt-menu')
        ->and($partial)->toContain('data-kt-menu-item-toggle="dropdown"')
        ->and($partial)->toContain('data-kt-menu-item-placement="right-start"')
        ->and($partial)->toContain('data-kt-menu-item-placement-rtl="left-start"')
        ->and($partial)->toContain('url($menu[\'url\'])')
        ->and($partial)->toContain('url($item[\'url\'])')
        ->and($partial)->toContain('url($child[\'url\'])')
        ->and($partial)->not->toContain('phc-m9-modules-sidebar')
        ->and($partial)->not->toContain('All Modules');
});

test('the metronic 9 preview route renders the generated demo3 blade', function () {
    $this
        ->get(route('theme.metronic9.preview'))
        ->assertOk()
        ->assertSee('Metronic 9 Demo3', false)
        ->assertSee('assets/metronic9/css/styles.css', false)
        ->assertSee('إيقاف M9', false);
});
