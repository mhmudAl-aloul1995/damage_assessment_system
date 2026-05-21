<?php

beforeEach(function () {
    config(['app.url' => 'http://localhost']);
});

test('legacy login php get redirects to laravel login route', function () {
    $this->get('/login.php')
        ->assertRedirect('/login');
});

test('legacy login php post redirects to laravel login route', function () {
    $this->post('/login.php')
        ->assertRedirect('/login');
});

test('legacy login php redirects include configured subdirectory path', function () {
    config(['app.url' => 'http://localhost']);

    $this->get('/login.php')
        ->assertRedirect('/login');
});
