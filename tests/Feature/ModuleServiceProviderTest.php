<?php

use App\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

it('registers the module service provider', function () {
    $providers = require base_path('bootstrap/providers.php');

    expect($providers)->toContain(ModuleServiceProvider::class);
});

it('loads module routes and view namespaces', function () {
    expect(Route::has('damageAssessment.index'))->toBeTrue()
        ->and(View::getFinder()->getHints())->toHaveKeys([
            'damage-assessment',
            'rubble',
        ]);
});
