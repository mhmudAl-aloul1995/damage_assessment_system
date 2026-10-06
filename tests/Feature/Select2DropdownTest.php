<?php

use Illuminate\Support\Facades\File;

it('provides a global Select2 initializer for static and dynamic dropdowns', function (): void {
    $layout = File::get(resource_path('views/layouts/app.blade.php'));
    $artisanCommandsView = File::get(resource_path('views/admin/artisan_commands/index.blade.php'));

    expect($layout)
        ->toContain("root.querySelectorAll('select')")
        ->toContain('MutationObserver')
        ->toContain('window.initializeSelect2Controls');

    expect($artisanCommandsView)->toContain('data-control="select2"');
});
