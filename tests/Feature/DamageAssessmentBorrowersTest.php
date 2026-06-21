<?php

use App\Models\User;
use App\Modules\DamageAssessmentBorrowers\Models\BorrowerBoqCatalogItem;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\DamageAssessmentBorrowers\Services\BorrowerRiskAnalysisService;
use App\Modules\DamageAssessmentBorrowers\Services\BorrowerSpreadsheetImportService;
use App\Support\Navigation\Sidebar;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;

it('allows field engineers to open the borrowers overview page', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('damage-assessment-borrowers.index'))
        ->assertOk()
        ->assertDontSee('<form id="borrowerSurveyForm"', false)
        ->assertSee('استيراد من Excel', false)
        ->assertSee('borrowersImportModal', false)
        ->assertSee('borrowers-import-dropzone', false)
        ->assertSee('اسحب ملف Excel هنا أو اضغط للاختيار', false)
        ->assertSee('تعبئة استبيان جديد', false)
        ->assertSee('استبيان المقترضين', false);
});

it('allows field engineers to open the borrower survey form page', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('damage-assessment-borrowers.create'))
        ->assertOk()
        ->assertSee('borrowerSurveyForm', false)
        ->assertSee('borrower-survey-form', false)
        ->assertSee('borrower-create-hero', false)
        ->assertSee('borrower-form-progress', false)
        ->assertSee('borrower-form-section-title', false)
        ->assertSee('data-offline-sync="true"', false)
        ->assertSee('window.phcOfflineSync.queue', false)
        ->assertSee('تعبئة استبيان المقترض', false)
        ->assertSee('بيانات المقترض الأساسية', false)
        ->assertSee('مناسب للجوال', false);
});

it('wires borrowers surveys into pwa offline caching and sync', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);
    $serviceWorker = file_get_contents(public_path('sw.js'));
    $backgroundSync = file_get_contents(public_path('background-sync.js'));
    $view = file_get_contents(base_path('app/Modules/DamageAssessmentBorrowers/views/index.blade.php'));

    expect(collect($manifest['shortcuts'] ?? [])->pluck('url'))->toContain('/damage-assessment-borrowers')
        ->and($serviceWorker)->toContain('PHC_CACHE_URLS')
        ->and($serviceWorker)->toContain("const CACHE_NAME = 'phc-pwa-v5'")
        ->and($serviceWorker)->toContain('APP_SCOPE_URL')
        ->and($serviceWorker)->toContain('cache.put(requestUrl.pathname, copy)')
        ->and($serviceWorker)->toContain('PHC_OFFLINE_SYNC_COMPLETE')
        ->and($backgroundSync)->toContain('cacheCurrentPage')
        ->and($backgroundSync)->toContain('PHC_PWA_URLS')
        ->and($view)->toContain('borrowersPendingRowsKey')
        ->and($view)->toContain('window.phcOfflineSync?.registerSync?.()')
        ->and($view)->toContain('damage-assessment-borrowers-page')
        ->and($view)->toContain('borrowersMobileList')
        ->and($view)->toContain('borrower-mobile-card');
});

it('serves pwa resources within the configured deployment path', function () {
    config(['app.url' => 'http://localhost/damage_assessment_system']);

    $this->get('/damage_assessment_system/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('scope', '/damage_assessment_system/')
        ->assertJsonPath('start_url', '/damage_assessment_system/login')
        ->assertJsonPath('shortcuts.0.url', '/damage_assessment_system/damage-assessment-borrowers')
        ->assertJsonPath('icons.5.src', '/damage_assessment_system/icon-192x192.png');

    $this->get('/damage_assessment_system/sw.js')
        ->assertOk()
        ->assertHeader('Service-Worker-Allowed', '/damage_assessment_system/')
        ->assertSee('phc-pwa-v5');

    $this->get('/damage_assessment_system/icon-192x192.png')
        ->assertOk();
});

it('stores borrower surveys through ajax and returns risk analysis', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->actingAs($user)
        ->postJson(route('damage-assessment-borrowers.store'), [
            'borrower_name' => 'Ahmad Saleh',
            'borrower_id_number' => '900000001',
            'family_members_count' => 7,
            'employment_status' => 'not_working',
            'is_borrower_alive' => false,
            'vulnerability_types' => ['disabled', 'elderly'],
            'guarantors_count' => 2,
            'guarantors_alive_status' => 'no',
            'guarantors_employment_statuses' => ['lost_job'],
            'displacement_status' => 'displaced',
            'displaced_to_governorate' => 'gaza',
            'loan_unit_occupancy_status' => 'none_due_damage',
            'loan_unit_damage_status' => 'destroyed',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('analysis.risk_level', 'critical');

    expect(DamageAssessmentBorrower::query()->where('borrower_id_number', '900000001')->exists())->toBeTrue();
});

it('imports an uploaded borrower workbook through ajax', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->mock(BorrowerSpreadsheetImportService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('importWorkbook')
            ->once()
            ->withArgs(fn (string $path): bool => is_file($path))
            ->andReturn([
                'total' => 3,
                'ready' => 2,
                'created' => 2,
                'updated' => 0,
                'skipped' => 1,
                'issues' => [],
                'duplicate_form_numbers' => 0,
                'risk_levels' => [
                    'critical' => 1,
                    'high' => 1,
                    'medium' => 0,
                    'low' => 0,
                ],
            ]);
    });

    $this->actingAs($user)
        ->post(route('damage-assessment-borrowers.import'), [
            'borrowers_file' => UploadedFile::fake()->create(
                'beneficiaries.xlsx',
                20,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('summary.created', 2)
        ->assertJsonPath('summary.skipped', 1);
});

it('lists borrower surveys as json rows', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    DamageAssessmentBorrower::query()->create([
        'submitted_by' => $user->id,
        'borrower_name' => 'Mona Borrower',
        'borrower_id_number' => '800000001',
        'is_borrower_alive' => true,
        'risk_level' => 'medium',
        'risk_score' => 33,
    ]);

    $this->actingAs($user)
        ->getJson(route('damage-assessment-borrowers.data', ['q' => 'Mona']))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.0.borrower_name', 'Mona Borrower')
        ->assertJsonPath('data.0.show_url', route('damage-assessment-borrowers.show', DamageAssessmentBorrower::query()->where('borrower_id_number', '800000001')->first()));
});

it('opens borrower details page with survey data attachments and boq items', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    $borrower = DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Details Borrower',
        'borrower_id_number' => '810000009',
        'form_number' => 'IDB-DETAIL',
        'phone_primary' => '0599000000',
        'is_borrower_alive' => true,
        'loan_unit_damage_status' => 'destroyed',
        'boq_total_usd' => 45,
        'boq_total_ils' => 144,
        'risk_level' => 'high',
        'risk_score' => 65,
        'risk_reasons' => ['High risk reason'],
    ]);
    $borrower->attachments()->create([
        'filename' => 'damage.jpg',
        'url' => 'https://example.test/damage.jpg',
        'source_index' => 1,
    ]);
    $borrower->boqItems()->create([
        'source_column' => 'Repair item',
        'source_key' => sha1('Repair item'),
        'description' => 'Repair item',
        'unit' => 'M2',
        'unit_price' => 15,
        'exchange_rate' => 3.2,
        'unit_price_ils' => 48,
        'quantity' => 3,
        'total_price' => 45,
        'total_price_ils' => 144,
        'sort_order' => 1,
    ]);
    $borrower->residentHouseholds()->create([
        'head_name' => 'Resident Household',
        'id_number' => '5005',
        'members_count' => 4,
        'phone' => '0599111111',
        'employment_status' => 'not_working',
        'source_index' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('damage-assessment-borrowers.show', $borrower))
        ->assertOk()
        ->assertSee('Details Borrower')
        ->assertSee('IDB-DETAIL')
        ->assertSee('damage.jpg')
        ->assertSee('https://example.test/damage.jpg')
        ->assertSee('Repair item')
        ->assertSee('Resident Household')
        ->assertSee('High risk reason');
});

it('opens borrower pricing page for database officers', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    $borrower = DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Pricing Borrower',
        'borrower_id_number' => '810000001',
        'is_borrower_alive' => true,
    ]);

    BorrowerBoqCatalogItem::query()->create([
        'item_code' => 'P1',
        'source_column' => 'Paint item',
        'source_key' => sha1('Paint item'),
        'description' => 'Paint item',
        'normalized_description' => 'paint item',
        'unit' => 'M2',
        'unit_price' => 7,
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('damage-assessment-borrowers.pricing', $borrower))
        ->assertOk()
        ->assertSee('borrowerPricingForm', false)
        ->assertSee('Paint item');
});

it('updates borrower pricing items and recalculates total', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    $borrower = DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Manual Pricing Borrower',
        'borrower_id_number' => '810000002',
        'is_borrower_alive' => true,
    ]);
    $catalogItem = BorrowerBoqCatalogItem::query()->create([
        'item_code' => 'P2',
        'source_column' => 'Door item',
        'source_key' => sha1('Door item'),
        'description' => 'Door item',
        'normalized_description' => 'door item',
        'unit' => 'عدد',
        'unit_price' => 50,
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->put(route('damage-assessment-borrowers.pricing.update', $borrower), [
            'exchange_rate' => 3.5,
            'items' => [
                [
                    'catalog_item_id' => $catalogItem->id,
                    'source_column' => 'Door item',
                    'source_key' => sha1('Door item'),
                    'item_code' => 'P2',
                    'description' => 'Door item',
                    'unit' => 'عدد',
                    'unit_price' => 55,
                    'quantity' => 2,
                    'sort_order' => 1,
                ],
            ],
        ])
        ->assertRedirect(route('damage-assessment-borrowers.pricing', $borrower));

    $borrower->refresh();

    expect((float) $borrower->boq_total_usd)->toBe(110.0)
        ->and((float) $borrower->boq_total_ils)->toBe(385.0)
        ->and((float) $borrower->exchange_rate)->toBe(3.5)
        ->and($borrower->boqItems()->count())->toBe(1)
        ->and((float) $borrower->boqItems()->first()->total_price)->toBe(110.0)
        ->and((float) $borrower->boqItems()->first()->total_price_ils)->toBe(385.0);
});

it('adds borrowers to the sidebar for database officers', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $module = Sidebar::forUser($user)->firstWhere('key', 'damage_assessment_borrowers');

    expect($module)->not->toBeNull()
        ->and($module['sections']->first()['url'])->toBe('damage-assessment-borrowers');
});

it('calculates borrower risk levels', function () {
    $analysis = app(BorrowerRiskAnalysisService::class)->analyze([
        'is_borrower_alive' => true,
        'employment_status' => 'working',
        'guarantors_alive_status' => 'yes',
        'guarantors_employment_statuses' => ['all_working'],
        'displacement_status' => 'resident',
        'loan_unit_damage_status' => 'minor',
    ]);

    expect($analysis['risk_level'])->toBe('low');
});

it('imports borrower boq items attachments and resident households', function () {
    BorrowerBoqCatalogItem::query()->create([
        'item_code' => '1.1',
        'description' => 'Repair concrete item',
        'normalized_description' => 'repair concrete item',
        'unit' => 'M2',
        'unit_price' => 10,
        'sort_order' => 1,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'borrower-import-related-').'.json';
    file_put_contents($path, json_encode([
        'records' => [
            [
                'row_number' => 2,
                'source_uuid' => 'uuid-related',
                'borrower_name' => 'Related Borrower',
                'borrower_id_number' => '9911',
                'attachments' => [
                    ['filename' => 'damage.jpg', 'url' => 'https://example.test/damage.jpg', 'source_index' => 1],
                ],
                'resident_households' => [
                    ['head_name' => 'Tenant Family', 'id_number' => '5001', 'members_count' => 3, 'phone' => '0590000000', 'employment_status' => 'not_working', 'source_index' => 1],
                ],
                'boq_quantities' => [
                    ['source_column' => 'Repair concrete item (M2)', 'quantity' => 2, 'sort_order' => 1],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        $summary = app(BorrowerSpreadsheetImportService::class)->import($path);
        $borrower = DamageAssessmentBorrower::query()->where('source_uuid', 'uuid-related')->sole();

        expect($summary['created'])->toBe(1)
            ->and($borrower->attachments()->count())->toBe(1)
            ->and($borrower->residentHouseholds()->count())->toBe(1)
            ->and($borrower->boqItems()->count())->toBe(1)
            ->and((float) $borrower->refresh()->boq_total_usd)->toBe(20.0)
            ->and((float) $borrower->boq_total_ils)->toBe(64.0)
            ->and($borrower->attachments_count)->toBe(1);
    } finally {
        @unlink($path);
    }
});

it('imports borrower records idempotently while skipping invalid and duplicate identities', function () {
    $path = tempnam(sys_get_temp_dir(), 'borrower-import-').'.json';
    file_put_contents($path, json_encode([
        'records' => [
            [
                'row_number' => 2,
                'source_uuid' => 'uuid-valid',
                'source_submission_id' => 1,
                'submitted_by_name' => 'ميداني',
                'surveyed_at' => '2026-05-23 10:00:00',
                'form_number' => 'IDB1',
                'borrower_name' => 'مستفيد صالح',
                'borrower_id_number' => '1001',
                'marital_status_label' => 'أعزب/ آنسة',
                'employment_status_label' => 'لا يعمل حاليا',
                'alive_label' => 'نعم',
                'guarantors_count' => 1,
                'guarantors_alive_label' => 'لا',
                'guarantors_employment_statuses' => ['lost_job'],
                'affected_guarantor_names' => ['كفيل متأثر'],
                'deceased_guarantor_names' => ['كفيل متوفى'],
                'displacement_status_label' => 'نازح',
                'displaced_to_governorate_label' => 'محافظة غزة',
                'loan_unit_occupancy_label' => 'لا يوجد (في حال الوحدة السكنية هدم كلي او بليغ غيرصالح للسكن)',
                'loan_unit_damage_label' => 'هدم كلي',
            ],
            [
                'row_number' => 3,
                'source_uuid' => 'uuid-duplicate-a',
                'borrower_name' => 'مستفيد مكرر أ',
                'borrower_id_number' => '2001',
                'employment_status_label' => 'متقاعد',
                'alive_label' => 'نعم',
            ],
            [
                'row_number' => 4,
                'source_uuid' => 'uuid-duplicate-b',
                'borrower_name' => 'مستفيد مكرر ب',
                'borrower_id_number' => '2001',
                'employment_status_label' => 'متقاعد',
                'alive_label' => 'نعم',
            ],
            [
                'row_number' => 5,
                'source_uuid' => 'uuid-invalid',
                'borrower_name' => 'مستفيد غير صالح',
                'borrower_id_number' => '3001',
                'employment_status_label' => 'نعم',
                'alive_label' => 'يوجد شهداء',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    try {
        $importer = app(BorrowerSpreadsheetImportService::class);
        $summary = $importer->import($path);
        $secondSummary = $importer->import($path);
        $borrower = DamageAssessmentBorrower::query()->where('source_uuid', 'uuid-valid')->sole();

        expect($summary['total'])->toBe(4)
            ->and($summary['ready'])->toBe(1)
            ->and($summary['created'])->toBe(1)
            ->and($summary['skipped'])->toBe(3)
            ->and($summary['risk_levels']['critical'])->toBe(1)
            ->and($secondSummary['created'])->toBe(0)
            ->and($secondSummary['updated'])->toBe(1)
            ->and(DamageAssessmentBorrower::query()->where('source_uuid', 'uuid-valid')->count())->toBe(1)
            ->and($borrower->source_uuid)->toBe('uuid-valid')
            ->and($borrower->employment_status)->toBe('not_working')
            ->and($borrower->marital_status)->toBe('single')
            ->and($borrower->loan_unit_damage_status)->toBe('destroyed')
            ->and($borrower->affected_guarantors)->toBe([['name' => 'كفيل متأثر', 'status' => 'lost_job']])
            ->and($borrower->deceased_guarantors)->toBe([['name' => 'كفيل متوفى']])
            ->and($borrower->risk_level)->toBe('critical');
    } finally {
        @unlink($path);
    }
});

it('updates an existing borrower by identity instead of skipping it', function () {
    DamageAssessmentBorrower::query()->create([
        'source_uuid' => 'old-uuid',
        'borrower_name' => 'Old Borrower Name',
        'borrower_id_number' => '7771',
        'is_borrower_alive' => true,
        'risk_level' => 'low',
        'risk_score' => 0,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'borrower-import-existing-').'.json';
    file_put_contents($path, json_encode([
        'records' => [
            [
                'row_number' => 2,
                'source_uuid' => 'new-uuid',
                'source_submission_id' => 10,
                'borrower_name' => 'Updated Borrower Name',
                'borrower_id_number' => '7771',
                'employment_status_label' => 'متقاعد',
                'alive_label' => 'نعم',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    try {
        $summary = app(BorrowerSpreadsheetImportService::class)->import($path);
        $borrower = DamageAssessmentBorrower::query()->where('borrower_id_number', '7771')->sole();

        expect($summary['ready'])->toBe(1)
            ->and($summary['created'])->toBe(0)
            ->and($summary['updated'])->toBe(1)
            ->and($summary['skipped'])->toBe(0)
            ->and($borrower->borrower_name)->toBe('Updated Borrower Name')
            ->and($borrower->source_uuid)->toBe('new-uuid')
            ->and($borrower->source_submission_id)->toBe(10)
            ->and($borrower->employment_status)->toBe('retired')
            ->and(DamageAssessmentBorrower::query()->where('borrower_id_number', '7771')->count())->toBe(1);
    } finally {
        @unlink($path);
    }
});
