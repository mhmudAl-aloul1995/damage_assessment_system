<?php

use App\Support\BrowsershotConfiguration;

uses(Tests\TestCase::class);

it('uses the configured chrome path when it exists', function () {
    $chromePath = storage_path('framework/testing-chrome.exe');

    touch($chromePath);

    try {
        config()->set('services.browsershot.chrome_path', $chromePath);

        expect(app(BrowsershotConfiguration::class)->chromePath())->toBe($chromePath);
    } finally {
        unlink($chromePath);
    }
});

it('ignores a configured chrome path when it does not exist', function () {
    config()->set('services.browsershot.chrome_path', storage_path('missing-chrome.exe'));

    $chromePath = app(BrowsershotConfiguration::class)->chromePath();

    expect($chromePath === null || is_file($chromePath))->toBeTrue();
});
