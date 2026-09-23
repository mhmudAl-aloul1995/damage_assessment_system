<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('renders the damage assessment dashboard preview page', function () {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'fake-token',
        ]),
    ]);

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
        ->assertSee('خريطة GIS شاملة')
        ->assertSee('كل المباني')
        ->assertSee('dashboard_preview_gis_map')
        ->assertSee('fake-token');
});
