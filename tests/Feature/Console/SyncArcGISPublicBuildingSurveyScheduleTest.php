<?php

use Illuminate\Support\Facades\Artisan;

it('registers the public building survey sync command in the scheduler', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toContain('sync:public-building-survey');
});
