<?php

// dd
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

test('application redirects to legacy login php are normalized to configured login route', function () {
    config(['app.url' => 'http://213.6.135.115/damage_assessment_system']);

    $this->get('/testing/legacy-login-redirect')
        ->assertRedirect('/damage_assessment_system/login');
});

test('application redirects with duplicated server base path are normalized', function () {
    config(['app.url' => 'http://213.6.135.115/damage_assessment_system']);

    $this->get('/testing/duplicated-base-redirect')
        ->assertStatus(302)
        ->assertHeader('Location', '/damage_assessment_system/damage-assessment/damageAssessment');
});
