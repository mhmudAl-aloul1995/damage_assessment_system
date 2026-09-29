<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('renders the damage assessment dashboard preview page', function (string $routeName): void {
    Http::fake([
        'https://www.arcgis.com/sharing/rest/generateToken' => Http::response([
            'token' => 'fake-token',
        ]),
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route($routeName));

    $response
        ->assertOk()
        ->assertViewIs('damage-assessment::dashboard.preview')
        ->assertSee('معاينة الصفحة الرئيسية')
        ->assertSee('المباني')
        ->assertSee('الوحدات السكانية')
        ->assertSee('منظمات المجتمع المدني')
        ->assertSee('المباني العامة')
        ->assertSee('الطرق')
        ->assertSee('خريطة GIS شاملة')
        ->assertSee('كل المباني')
        ->assertSee('dashboard_preview_gis_map')
        ->assertSee('fake-token')
        ->assertSee('معاينة تصميم · بيانات توضيحية')
        ->assertSee('الأرقام والرسوم توضيحية ولا تمثل تقارير النظام.')
        ->assertSeeInOrder(['نظرة على نطاق العمل', 'المشهد العام في مكان واحد', 'خريطة GIS شاملة', 'تحتاج انتباهك', 'قراءة في الأضرار'])
        ->assertSee('معاينة Metronic')
        ->assertSee('href="'.route('damageAssessment.index').'"', false)
        ->assertSee('href="'.route('public-buildings.index', ['uxo_only' => 1]).'"', false)
        ->assertSee('href="'.route('cso-surveys.index', ['without_units' => 1]).'"', false)
        ->assertDontSee('آخر تحديث: اليوم');

    $document = new DOMDocument;
    $previousErrorHandling = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorHandling);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-preview-sector]'))->toHaveCount(6);
    expect($xpath->query('//*[@data-preview-sector and @aria-selected="true"]'))->toHaveCount(1);

    foreach (['overview', 'buildings', 'housing', 'cso', 'public', 'roads'] as $sector) {
        $tab = $xpath->query('//*[@id="preview-tab-'.$sector.'"]')->item(0);
        $pane = $xpath->query('//*[@id="preview-pane-'.$sector.'"]')->item(0);

        expect($tab)->not->toBeNull();
        expect($pane)->not->toBeNull();
        expect($tab->getAttribute('aria-controls'))->toBe($pane->getAttribute('id'));
        expect($pane->getAttribute('aria-labelledby'))->toBe($tab->getAttribute('id'));
    }

    foreach (['building.index', 'housing.index', 'cso-surveys.index', 'public-buildings.index', 'road-facilities.index'] as $routeName) {
        $response->assertSee('href="'.route($routeName).'"', false);
    }
})->with(['damageAssessment.preview', 'damageAssessment.dashboard-preview']);

it('requires authentication to view the dashboard design preview', function (string $routeName): void {
    $this->get(route($routeName))
        ->assertRedirect(route('login'));
})->with(['damageAssessment.preview', 'damageAssessment.dashboard-preview']);
