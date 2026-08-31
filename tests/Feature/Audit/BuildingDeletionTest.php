<?php

use Illuminate\Support\Facades\Route;

it('does not register the legacy direct building deletion routes', function () {
    expect(Route::has('audit.building.delete.schedule'))->toBeFalse()
        ->and(Route::has('audit.building.delete.undo'))->toBeFalse()
        ->and(Route::has('audit.building.delete.commit'))->toBeFalse();
});

it('keeps the housing unit deletion routes registered', function () {
    expect(Route::has('housing.assessment.delete.schedule'))->toBeTrue()
        ->and(Route::has('housing.assessment.delete.undo'))->toBeTrue()
        ->and(Route::has('housing.assessment.delete.commit'))->toBeTrue();
});
