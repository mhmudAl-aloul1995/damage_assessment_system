<?php

test('legacy login php get redirects to laravel login route', function () {
    $this->get('/login.php')
        ->assertRedirect('/login');
});

test('legacy login php post redirects to laravel login route', function () {
    $this->post('/login.php')
        ->assertRedirect('/login');
});
