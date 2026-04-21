<?php

use Illuminate\Support\Facades\Artisan;

it('registers the road facility survey sync command in the scheduler', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('sync:road-facility-survey');
});
