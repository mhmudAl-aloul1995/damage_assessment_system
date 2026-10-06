<?php

use Illuminate\Support\Facades\File;

it('provides global Flatpickr controls for dates and date ranges', function (): void {
    $layout = File::get(resource_path('views/layouts/app.blade.php'));

    expect($layout)
        ->toContain('window.flatpickr')
        ->toContain("mode: 'range'")
        ->toContain('input[type="date"]')
        ->toContain('$.fn.daterangepicker = function');
});
