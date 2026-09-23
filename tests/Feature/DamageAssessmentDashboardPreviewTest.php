<?php

use App\Models\User;

it('renders the damage assessment dashboard preview page', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('damageAssessment.preview'));

    $response
        ->assertOk()
        ->assertSee('معاينة الصفحة الرئيسية')
        ->assertSee('المباني')
        ->assertSee('الوحدات السكانية')
        ->assertSee('منظمات المجتمع المدني')
        ->assertSee('المباني العامة')
        ->assertSee('الطرق')
        ->assertSee('خرائط ArcGIS المرتبطة بالجداول');
});
