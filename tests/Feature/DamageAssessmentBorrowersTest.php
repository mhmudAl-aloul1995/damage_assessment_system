<?php

use App\Exports\BorrowerReportExport;
use App\Models\KoboRestSubmission;
use App\Models\User;
use App\Modules\DamageAssessmentBorrowers\Models\BorrowerBoqCatalogItem;
use App\Modules\DamageAssessmentBorrowers\Models\BorrowerPricingSetting;
use App\Modules\DamageAssessmentBorrowers\Models\DamageAssessmentBorrower;
use App\Modules\DamageAssessmentBorrowers\Services\BorrowerDuplicateMergeService;
use App\Modules\DamageAssessmentBorrowers\Services\BorrowerRiskAnalysisService;
use App\Modules\DamageAssessmentBorrowers\Services\BorrowerSpreadsheetImportService;
use App\Support\Navigation\Sidebar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use Mockery\MockInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
        ->assertSee('borrowersExportModal', false)
        ->assertSee(str_replace('/', '\/', route('damage-assessment-borrowers.export')), false)
        ->assertSee('borrowers-import-dropzone', false)
        ->assertSee('borrowersPreviewBtn', false)
        ->assertSee('borrowersImportPreview', false)
        ->assertSee("previewData.append('_token', csrfToken())", false)
        ->assertSee('borrower-command-center', false)
        ->assertSee('borrowerRiskFilter', false)
        ->assertSee('data-risk-filter="critical"', false)
        ->assertSee('data-stat="partial_damage"', false)
        ->assertSee('data-damage-filter="partial"', false)
        ->assertSee('borrowerDamageFilter', false)
        ->assertSee('borrower-filter-select', false)
        ->assertSee('borrower-worklist-toolbar', false)
        ->assertSee('صافي مبلغ القرض', false)
        ->assertSee('المبلغ الكلي', false)
        ->assertSee('الرصيد الإجمالي الحالي', false)
        ->assertSee('badge-light-${color}', false)
        ->assertSee("'danger'", false)
        ->assertDontSee('<th>صافي مبلغ القرض</th>', false)
        ->assertDontSee('<th>الرصيد الإجمالي الحالي</th>', false)
        ->assertSee("$('.borrower-filter-select').each", false)
        ->assertSee('اسحب ملف Excel هنا أو اضغط للاختيار', false)
        ->assertSee('تصدير التقرير', false)
        ->assertSee('تقرير مختصر مطابق للقالب', false)
        ->assertSee('تعبئة استبيان جديد', false)
        ->assertSee('استبيان المقترضين', false);
});

it('allows borrower managers to manually sync iqrad kobo edits', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    Artisan::shouldReceive('call')
        ->once()
        ->with('kobo:sync-iqrad-borrowers', ['--all' => true])
        ->andReturn(0);

    $this->actingAs($user)
        ->get(route('damage-assessment-borrowers.index'))
        ->assertOk()
        ->assertSee('تحديث من Kobo الآن', false)
        ->assertSee(route('damage-assessment-borrowers.kobo.sync'), false);

    $this->actingAs($user)
        ->post(route('damage-assessment-borrowers.kobo.sync'))
        ->assertRedirect(route('damage-assessment-borrowers.index'))
        ->assertSessionHas('success', 'تم سحب آخر تعديلات Kobo وتحديث بيانات المقترضين وجدول الكميات.');
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
        ->assertSee('data-scroll-to-section="borrowerBasics"', false)
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
        ->and($view)->toContain('borrower-mobile-card')
        ->and($view)->toContain('borrower-pricing-cell')
        ->and($view)->toContain('pricingSummary(row)');
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
            'loan_unit_area' => 100,
            'loan_unit_floor_type' => 'repeated',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('analysis.risk_level', 'critical');

    $borrower = DamageAssessmentBorrower::query()->where('borrower_id_number', '900000001')->sole();

    expect((float) $borrower->boq_total_usd)->toBe(28000.0)
        ->and($borrower->loan_unit_floor_type)->toBe('repeated');
});

it('updates an existing borrower survey instead of duplicating the beneficiary', function () {
    $role = Role::findOrCreate('Field Engineer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $payload = [
        'borrower_name' => 'Original Borrower',
        'borrower_id_number' => '900000002',
        'is_borrower_alive' => true,
        'employment_status' => 'working',
        'loan_unit_damage_status' => 'minor',
    ];

    $this->actingAs($user)
        ->postJson(route('damage-assessment-borrowers.store'), $payload)
        ->assertOk()
        ->assertJsonPath('status', true);

    $this->actingAs($user)
        ->postJson(route('damage-assessment-borrowers.store'), array_merge($payload, [
            'borrower_name' => 'Updated Borrower',
            'employment_status' => 'not_working',
        ]))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('borrower.borrower_name', 'Updated Borrower');

    expect(DamageAssessmentBorrower::query()->where('borrower_id_number', '900000002')->count())->toBe(1);

    $borrower = DamageAssessmentBorrower::query()->where('borrower_id_number', '900000002')->sole();

    expect($borrower->borrower_name)->toBe('Updated Borrower')
        ->and($borrower->employment_status)->toBe('not_working');
});

it('merges all duplicate borrower rows into one complete beneficiary record', function () {
    Schema::table('damage_assessment_borrowers', function ($table): void {
        $table->dropUnique('damage_assessment_borrowers_borrower_id_number_unique');
    });

    $loanRow = DamageAssessmentBorrower::query()->create([
        'source_uuid' => 'loan-row',
        'borrower_name' => 'Loan Borrower',
        'borrower_id_number' => '900000003',
        'loan_number' => '0000900999',
        'loan_status' => 'active',
        'loan_total_amount' => 30000,
        'loan_balance' => 13750,
        'is_borrower_alive' => true,
        'risk_level' => 'low',
        'risk_score' => 0,
        'notes' => 'Loan note',
        'updated_at' => now()->subDay(),
    ]);

    $surveyRow = DamageAssessmentBorrower::query()->create([
        'source_uuid' => 'survey-row',
        'borrower_name' => 'Survey Borrower',
        'borrower_id_number' => '900000003',
        'is_borrower_alive' => true,
        'displacement_status' => 'displaced',
        'loan_unit_damage_status' => 'destroyed',
        'vulnerability_types' => ['disabled'],
        'risk_level' => 'critical',
        'risk_score' => 80,
        'risk_reasons' => ['High risk reason'],
        'notes' => 'Survey note',
        'updated_at' => now(),
    ]);

    $loanRow->attachments()->create([
        'filename' => 'loan.jpg',
        'url' => 'https://example.test/loan.jpg',
        'source_index' => 1,
    ]);

    $surveyRow->attachments()->create([
        'filename' => 'survey.jpg',
        'url' => 'https://example.test/survey.jpg',
        'source_index' => 2,
    ]);

    $loanRow->boqItems()->create([
        'source_column' => 'Loan BOQ',
        'source_key' => 'loan-boq',
        'description' => 'Loan BOQ',
        'quantity' => 1,
        'unit_price' => 10,
        'total_price' => 10,
        'sort_order' => 1,
    ]);

    app(BorrowerDuplicateMergeService::class)->merge();

    expect(DamageAssessmentBorrower::query()->where('borrower_id_number', '900000003')->count())->toBe(1);

    $borrower = DamageAssessmentBorrower::query()
        ->where('borrower_id_number', '900000003')
        ->with(['attachments', 'boqItems'])
        ->sole();

    expect($borrower->borrower_name)->toBe('Survey Borrower')
        ->and($borrower->loan_number)->toBe('0000900999')
        ->and((float) $borrower->loan_balance)->toBe(13750.0)
        ->and($borrower->displacement_status)->toBe('displaced')
        ->and($borrower->loan_unit_damage_status)->toBe('destroyed')
        ->and($borrower->vulnerability_types)->toBe(['disabled'])
        ->and($borrower->risk_level)->toBe('critical')
        ->and($borrower->risk_score)->toBe(80)
        ->and($borrower->notes)->toContain('Loan note')
        ->and($borrower->notes)->toContain('Survey note')
        ->and($borrower->attachments)->toHaveCount(2)
        ->and($borrower->boqItems)->toHaveCount(1)
        ->and($borrower->attachments_count)->toBe(2);
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

it('previews and imports active Kuwait loan records from the selected worksheet', function () {
    $spreadsheet = new Spreadsheet;
    $activeSheet = $spreadsheet->getActiveSheet();
    $activeSheet->setTitle('نشطه');
    $activeSheet->fromArray([
        ['رقم', 'قرض', 'مقترض', '', 'مقترض', '', '', 'تاريخ التفعيل', 'تاريخ اخر قسط', 'مبلغ القرض', 'محفظة القرض', 'صافي مبلغ القرض', 'الرصيد الاجمالي الحالي'],
        ['', '', '', 'اصل القرض', 'الجوال', 'الاسم', 'العنوان'],
        [1, '0000900101', '930046990', 32280, '0599496880', 'سعد كساب', 'خانيونس', 43101, 46323, '26,512.00', '15,729.37', 26512, '15,721.19'],
    ]);
    $closedSheet = $spreadsheet->createSheet();
    $closedSheet->setTitle('مغلقه');
    $closedSheet->fromArray(array_fill(0, 5, []));
    $closedSheet->fromArray([
        ['رقم', 'قرض', 'مقترض', 'الاسم', 'اصل القرض', 'رقم الجوال', 'العنوان', 'المبلغ الكلي', 'عدد دفعات السداد', 'تاريخ بداية السداد', 'المبلغ المطلوب', 'قيمة السداد المدفوعة', 'الرصيد الكلي', 'سلّمت برائة الذّمة'],
    ], null, 'A6');

    $path = tempnam(sys_get_temp_dir(), 'kuwait-loans-');
    (new Xlsx($spreadsheet))->save($path);

    $importer = app(BorrowerSpreadsheetImportService::class);
    $preview = $importer->previewLoanWorkbook($path);
    $summary = $importer->importLoanWorkbook($path, 'نشطه');

    expect($preview['source'])->toBe('kuwait-loans')
        ->and($preview['sheets'][0]['name'])->toBe('نشطه')
        ->and($preview['sheets'][0]['ready'])->toBe(1)
        ->and($summary['created'])->toBe(1);

    $borrower = DamageAssessmentBorrower::query()->where('borrower_id_number', '930046990')->sole();

    expect($borrower->loan_number)->toBe('0000900101')
        ->and($borrower->loan_status)->toBe('active')
        ->and((float) $borrower->loan_original_amount)->toBe(32280.0)
        ->and((float) $borrower->loan_portfolio_amount)->toBe(15729.37)
        ->and((float) $borrower->loan_net_amount)->toBe(26512.0)
        ->and((float) $borrower->loan_balance)->toBe(15721.19);

    unlink($path);
});

it('previews borrower survey workbooks without requiring Kuwait loan sheets', function () {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Heks Final V1');
    $sheet->fromArray([
        ['_uuid', 'اسم المقترض/ة رباعي:', 'رقم هوية المقترض/ة:', 'الطابق'],
        ['uuid-1', 'مقترض تجريبي', '900000001', 'متكرر'],
    ]);

    $path = tempnam(sys_get_temp_dir(), 'borrower-survey-');
    (new Xlsx($spreadsheet))->save($path);

    $preview = app(BorrowerSpreadsheetImportService::class)->previewWorkbook($path);

    expect($preview['source'])->toBe('borrower-survey')
        ->and($preview['sheets'][0]['name'])->toBe('Heks Final V1')
        ->and($preview['sheets'][0]['status'])->toBe('survey')
        ->and($preview['sheets'][0]['ready'])->toBe(1)
        ->and($preview['sheets'][0]['sample'][0]['loan_unit_floor_type'])->toBe('متكرر');

    unlink($path);
});

it('lists borrower surveys as json rows', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $visitedBorrower = DamageAssessmentBorrower::query()->create([
        'submitted_by' => $user->id,
        'borrower_name' => 'Mona Borrower',
        'borrower_id_number' => '800000001',
        'is_borrower_alive' => true,
        'is_inside_yellow_line' => true,
        'loan_balance' => 4908,
        'loan_total_amount' => 28075,
        'loan_portfolio_amount' => 4896.81,
        'loan_net_amount' => 28075,
        'risk_level' => 'medium',
        'risk_score' => 33,
        'loan_unit_damage_status' => 'minor',
    ]);

    DamageAssessmentBorrower::query()->create([
        'submitted_by' => $user->id,
        'borrower_name' => 'Local Damage Borrower',
        'borrower_id_number' => '800000002',
        'is_borrower_alive' => true,
        'loan_unit_damage_status' => 'destroyed',
    ]);

    KoboRestSubmission::query()->create([
        'service_name' => 'iqrad',
        'submission_uuid' => 'uuid-borrower-visited-001',
        'payload' => [
            'borrower_name' => 'Mona Borrower',
            'group_lv9gw32' => ['__007' => '1'],
        ],
        'damage_assessment_borrower_id' => $visitedBorrower->id,
        'sync_status' => 'synced',
    ]);

    KoboRestSubmission::query()->create([
        'service_name' => 'iqrad',
        'submission_uuid' => 'uuid-borrower-visited-002',
        'payload' => [
            'borrower_name' => 'Mona Borrower',
            'group_lv9gw32' => ['__007' => '4'],
        ],
        'damage_assessment_borrower_id' => $visitedBorrower->id,
        'sync_status' => 'synced',
    ]);

    $this->actingAs($user)
        ->getJson(route('damage-assessment-borrowers.data', ['q' => 'Mona', 'damage_status' => 'partial']))
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.0.borrower_name', 'Mona Borrower')
        ->assertJsonPath('data.0.loan_balance', 4908)
        ->assertJsonPath('data.0.loan_portfolio_amount', 4896.81)
        ->assertJsonPath('stats.partial_damage', 1)
        ->assertJsonPath('stats.visited_total', 2)
        ->assertJsonPath('stats.inside_yellow_line', 1)
        ->assertJsonPath('stats.visited_destroyed', 1)
        ->assertJsonPath('stats.visited_partial_damage', 1)
        ->assertJsonPath('data.0.show_url', route('damage-assessment-borrowers.show', DamageAssessmentBorrower::query()->where('borrower_id_number', '800000001')->first()));
});

it('filters borrower worklist to full demolition damage only', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Destroyed Borrower',
        'borrower_id_number' => '800000011',
        'is_borrower_alive' => true,
        'loan_unit_damage_status' => 'destroyed',
    ]);

    DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Loan Only Borrower',
        'borrower_id_number' => '800000012',
        'is_borrower_alive' => true,
        'loan_unit_damage_status' => null,
    ]);

    DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Minor Damage Borrower',
        'borrower_id_number' => '800000013',
        'is_borrower_alive' => true,
        'loan_unit_damage_status' => 'minor',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('damage-assessment-borrowers.data', ['damage_status' => 'destroyed']))
        ->assertOk()
        ->assertJsonPath('status', true);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.borrower_name'))->toBe('Destroyed Borrower')
        ->and($response->json('data.0.loan_unit_damage_status'))->toBe('destroyed');
});

it('syncs borrower form numbers from Excel by identity number', function () {
    $borrower = DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Form Number Borrower',
        'borrower_id_number' => '800000021',
        'is_borrower_alive' => true,
    ]);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('القائمة الاولية');
    $sheet->fromArray([
        ['الرقم', 'رقم الاستمارة', 'رقم هوية المقترض', 'الاسم'],
        [1, 'IDB21', '800000021', 'Form Number Borrower'],
        [2, 'IDB22', '800000022', 'Missing Borrower'],
    ]);

    $path = tempnam(sys_get_temp_dir(), 'borrower-form-numbers-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    try {
        $this->artisan('borrowers:sync-form-numbers', ['file' => $path])
            ->assertSuccessful();

        expect($borrower->refresh()->form_number)->toBe('IDB21');
    } finally {
        @unlink($path);
    }
});

it('can prune borrowers missing from the selected form number sheet', function () {
    $keptBorrower = DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB 31',
        'borrower_name' => 'Kept Borrower',
        'borrower_id_number' => '800000099',
        'is_borrower_alive' => true,
    ]);

    DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB32',
        'borrower_name' => 'Deleted Borrower',
        'borrower_id_number' => '800000032',
        'is_borrower_alive' => true,
    ]);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('الحصر الجديد');
    $sheet->fromArray([
        ['الرقم', 'رقم الاستمارة', 'رقم هوية المقترض', 'الاسم'],
        [1, 'IDB31', '800000031', 'Kept Borrower'],
    ]);

    $path = tempnam(sys_get_temp_dir(), 'borrower-form-number-prune-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    try {
        $this->artisan('borrowers:sync-form-numbers', [
            'file' => $path,
            '--sheet' => 'الحصر الجديد',
            '--delete-missing-from-sheet' => true,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Deleted Borrower')
            ->assertSuccessful();

        expect(DamageAssessmentBorrower::query()->orderBy('borrower_id_number')->pluck('borrower_id_number')->all())->toBe(['800000032', '800000099'])
            ->and($keptBorrower->refresh()->form_number)->toBe('IDB 31');
    } finally {
        @unlink($path);
    }
});

it('can prune borrowers missing from column c identity numbers', function () {
    DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB31',
        'borrower_name' => 'Kept Borrower',
        'borrower_id_number' => '800000031',
        'is_borrower_alive' => true,
    ]);

    DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB32',
        'borrower_name' => 'Deleted Borrower',
        'borrower_id_number' => '800000032',
        'is_borrower_alive' => true,
    ]);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('inventory');
    $sheet->fromArray([
        ['ط§ظ„ط±ظ‚ظ…', 'ط±ظ‚ظ… ط§ظ„ط§ط³طھظ…ط§ط±ط©', 'ط±ظ‚ظ… ظ‡ظˆظٹط© ط§ظ„ظ…ظ‚طھط±ط¶', 'ط§ظ„ط§ط³ظ…'],
        [1, 'IDB32', '800000031', 'Kept Borrower'],
    ]);

    $path = tempnam(sys_get_temp_dir(), 'borrower-identity-prune-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    try {
        $exitCode = Artisan::call('borrowers:sync-form-numbers', [
            'file' => $path,
            '--sheet' => 'inventory',
            '--delete-missing-from-sheet' => true,
            '--delete-missing-by-identity' => true,
        ]);

        expect($exitCode)->toBe(0)
            ->and(Artisan::output())->toContain('Deleted borrowers')
            ->and(DamageAssessmentBorrower::query()->pluck('borrower_id_number')->all())->toBe(['800000031']);
    } finally {
        @unlink($path);
    }
});

it('can enforce the selected sheet by form number and remove duplicates', function () {
    DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB31',
        'borrower_name' => 'Kept Borrower',
        'borrower_id_number' => '800000031',
        'is_borrower_alive' => true,
    ]);

    DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB 31',
        'borrower_name' => 'Duplicate Borrower',
        'borrower_id_number' => '800000099',
        'is_borrower_alive' => true,
    ]);

    DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB32',
        'borrower_name' => 'Missing Form Borrower',
        'borrower_id_number' => '800000032',
        'is_borrower_alive' => true,
    ]);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('inventory');
    $sheet->fromArray([
        ['id', 'form_number', 'identity_number', 'name'],
        [1, 'idb31', '800000031', 'Kept Borrower'],
    ]);

    $path = tempnam(sys_get_temp_dir(), 'borrower-form-enforce-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    try {
        $exitCode = Artisan::call('borrowers:sync-form-numbers', [
            'file' => $path,
            '--sheet' => 'inventory',
            '--delete-missing-from-sheet' => true,
            '--dedupe-form-numbers' => true,
        ]);

        expect($exitCode)->toBe(0)
            ->and(DamageAssessmentBorrower::query()->count())->toBe(1)
            ->and(strtoupper(str_replace(' ', '', (string) DamageAssessmentBorrower::query()->first()?->form_number)))->toBe('IDB31');
    } finally {
        @unlink($path);
    }
});

it('syncs inside yellow line flag from column h by form number', function () {
    $insideBorrower = DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB31',
        'borrower_name' => 'Inside Borrower',
        'borrower_id_number' => '800000099',
        'is_borrower_alive' => true,
    ]);

    $outsideBorrower = DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB32',
        'borrower_name' => 'Outside Borrower',
        'borrower_id_number' => '800000098',
        'is_borrower_alive' => true,
    ]);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('inventory');
    $sheet->fromArray([
        ['id', 'form_number', 'identity_number', 'name', 'phone', 'address', 'notes', 'yellow_line'],
        [1, 'IDB31', '800000031', 'Inside Borrower', null, null, null, 'لا'],
        [2, 'IDB32', '800000032', 'Outside Borrower', null, null, null, 'نعم*'],
    ]);

    $path = tempnam(sys_get_temp_dir(), 'borrower-yellow-line-').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    try {
        $exitCode = Artisan::call('borrowers:sync-form-numbers', [
            'file' => $path,
            '--sheet' => 'inventory',
        ]);

        expect($exitCode)->toBe(0)
            ->and($insideBorrower->refresh()->is_inside_yellow_line)->toBeTrue()
            ->and($outsideBorrower->refresh()->is_inside_yellow_line)->toBeFalse();
    } finally {
        @unlink($path);
    }
});

it('exports borrower report using the current filters', function () {
    Excel::fake();

    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $exportBorrower = DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB-EXPORT',
        'borrower_name' => 'Export Borrower',
        'borrower_id_number' => '820000001',
        'loan_total_amount' => 28075,
        'loan_balance' => 4908,
        'is_borrower_alive' => true,
        'loan_unit_area' => 260,
        'loan_unit_floor_type' => 'ground',
        'loan_unit_damage_status' => 'destroyed',
        'boq_total_usd' => 84500,
        'boq_total_ils' => 245050,
        'risk_level' => 'high',
        'risk_score' => 70,
        'attachments_count' => 2,
        'notes' => 'Export notes',
    ]);

    DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB-LOCAL-DESTROYED',
        'borrower_name' => 'Local Destroyed Borrower',
        'borrower_id_number' => '820000099',
        'is_borrower_alive' => true,
        'loan_unit_damage_status' => 'destroyed',
    ]);

    DamageAssessmentBorrower::query()->create([
        'form_number' => 'IDB-SKIP',
        'borrower_name' => 'Partial Borrower',
        'borrower_id_number' => '820000002',
        'is_borrower_alive' => true,
        'loan_unit_damage_status' => 'minor',
    ]);

    KoboRestSubmission::query()->create([
        'service_name' => 'iqrad',
        'submission_uuid' => 'uuid-export-destroyed',
        'payload' => ['group_lv9gw32/__007' => '1'],
        'damage_assessment_borrower_id' => $exportBorrower->id,
        'sync_status' => 'synced',
    ]);

    $this->actingAs($user)
        ->get(route('damage-assessment-borrowers.export', [
            'damage_status' => 'destroyed',
            'report_type' => 'compact',
        ]))
        ->assertOk();

    Excel::assertDownloaded('borrowers-report-'.now()->format('Y-m-d-His').'.xlsx', function (BorrowerReportExport $export): bool {
        return $export->headings() === [
            'الكود',
            'كود المستفيد',
            'اسم المقترض',
            'رقم الهوية',
            'قيمة القرض',
            'المبلغ المتبقي',
            'قيمة الضرر للهدم الكلي',
            'نوع الضرر',
            'الملاحظات',
        ]
            && $export->collection()->count() === 1
            && $export->map($export->collection()->first())[0] === 'IDB-EXPORT'
            && $export->map($export->collection()->first())[1] === 'IDB-EXPORT'
            && $export->map($export->collection()->first())[6] === 84500.0;
    });
});

it('opens borrower details page with survey data attachments and boq items', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    $borrower = DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Details Borrower',
        'borrower_id_number' => '810000009',
        'form_number' => 'IDB-DETAIL',
        'loan_number' => '0000900101',
        'loan_status' => 'active',
        'loan_original_amount' => 32280,
        'loan_total_amount' => 28075,
        'loan_portfolio_amount' => 4896.81,
        'loan_net_amount' => 28075,
        'loan_balance' => 15721.19,
        'phone_primary' => '0599000000',
        'is_borrower_alive' => true,
        'loan_unit_area' => 260,
        'loan_unit_floor_type' => 'ground',
        'location_latitude' => 31.5012345,
        'location_longitude' => 34.4667891,
        'location_precision' => 4.5,
        'loan_unit_damage_status' => 'destroyed',
        'boq_total_usd' => 84500,
        'boq_total_ils' => 245050,
        'risk_level' => 'high',
        'risk_score' => 65,
        'risk_reasons' => ['High risk reason'],
    ]);
    $attachment = $borrower->attachments()->create([
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
    $borrower->koboAnswers()->create([
        'field_hash' => sha1('group/custom_question'),
        'field_key' => 'group/custom_question',
        'field_label' => 'custom question',
        'value' => 'Custom Kobo answer',
        'raw_value' => 'Custom Kobo answer',
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('damage-assessment-borrowers.show', $borrower))
        ->assertOk()
        ->assertSee('Details Borrower')
        ->assertSee('IDB-DETAIL')
        ->assertSee('بيانات القرض')
        ->assertSee('0000900101')
        ->assertSee('15,721.19')
        ->assertSee('قيمة الضرر للهدم الكلي')
        ->assertSee('84,500.00')
        ->assertSee('محفظة القرض')
        ->assertSee('4,896.81')
        ->assertSee('damage.jpg')
        ->assertSee(route('damage-assessment-borrowers.attachments.show', [$borrower, $attachment]))
        ->assertSee('www.openstreetmap.org/export/embed.html', false)
        ->assertSee('https://www.google.com/maps?q=31.5012345,34.4667891', false)
        ->assertSee('Repair item')
        ->assertSee('Resident Household')
        ->assertSee('group/custom_question')
        ->assertSee('Custom Kobo answer')
        ->assertSee('High risk reason');
});

it('shows a clear full demolition valuation warning when floor type is missing', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $borrower = DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Missing Floor Borrower',
        'borrower_id_number' => '810000029',
        'is_borrower_alive' => true,
        'loan_unit_area' => 260,
        'loan_unit_damage_status' => 'destroyed',
        'boq_total_usd' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('damage-assessment-borrowers.show', $borrower))
        ->assertOk()
        ->assertSee('قيمة الضرر للهدم الكلي')
        ->assertSee('يلزم تحديد الطابق لحساب قيمة الضرر.');
});

it('streams borrower attachments through the application with a kobo token', function () {
    config(['services.kobotoolbox.token' => 'secret-token']);
    Http::fake([
        'https://kf.kobotoolbox.org/api/v2/assets/example/data/1/attachments/att1/' => Http::response('image-binary', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    $borrower = DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Attachment Borrower',
        'borrower_id_number' => '810000019',
        'is_borrower_alive' => true,
    ]);
    $attachment = $borrower->attachments()->create([
        'filename' => 'damage.jpg',
        'url' => 'https://kf.kobotoolbox.org/api/v2/assets/example/data/1/attachments/att1/',
        'source_index' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('damage-assessment-borrowers.attachments.show', [$borrower, $attachment]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertSee('image-binary');

    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Token secret-token'));
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
        ->assertSee('pricing-table', false)
        ->assertSee('pricing-col-item', false)
        ->assertSee('pricing-page-header', false)
        ->assertSee('pricingSearchInput', false)
        ->assertSee('pricingActiveOnlyToggle', false)
        ->assertSee('تغيير سعر الصرف هنا يعيد احتساب قيمة الشيكل لكل استبيانات المقترضين')
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
    $otherBorrower = DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Other Pricing Borrower',
        'borrower_id_number' => '810000003',
        'is_borrower_alive' => true,
        'boq_total_usd' => 40,
        'exchange_rate' => 3.2,
        'boq_total_ils' => 128,
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
    $otherBorrower->boqItems()->create([
        'source_column' => 'Window item',
        'source_key' => sha1('Window item'),
        'description' => 'Window item',
        'unit' => 'M2',
        'unit_price' => 20,
        'exchange_rate' => 3.2,
        'unit_price_ils' => 64,
        'quantity' => 2,
        'total_price' => 40,
        'total_price_ils' => 128,
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
    $otherBorrower->refresh();

    expect((float) $borrower->boq_total_usd)->toBe(110.0)
        ->and((float) $borrower->boq_total_ils)->toBe(385.0)
        ->and((float) $borrower->exchange_rate)->toBe(3.5)
        ->and($borrower->boqItems()->count())->toBe(1)
        ->and((float) $borrower->boqItems()->first()->total_price)->toBe(110.0)
        ->and((float) $borrower->boqItems()->first()->total_price_ils)->toBe(385.0)
        ->and((float) $otherBorrower->exchange_rate)->toBe(3.5)
        ->and((float) $otherBorrower->boq_total_ils)->toBe(140.0)
        ->and((float) $otherBorrower->boqItems()->first()->unit_price_ils)->toBe(70.0)
        ->and((float) $otherBorrower->boqItems()->first()->total_price_ils)->toBe(140.0);
});

it('updates the global borrower exchange rate from the main screen', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);
    $borrower = DamageAssessmentBorrower::query()->create([
        'borrower_name' => 'Global Rate Borrower',
        'borrower_id_number' => '810000004',
        'is_borrower_alive' => true,
        'boq_total_usd' => 40,
        'exchange_rate' => 3.2,
        'boq_total_ils' => 128,
    ]);
    $catalogItem = BorrowerBoqCatalogItem::query()->create([
        'item_code' => 'P3',
        'source_column' => 'Global rate item',
        'source_key' => sha1('Global rate item'),
        'description' => 'Global rate item',
        'normalized_description' => 'global rate item',
        'unit' => 'M2',
        'unit_price' => 20,
        'unit_price_ils' => 64,
        'sort_order' => 1,
    ]);
    $borrower->boqItems()->create([
        'catalog_item_id' => $catalogItem->id,
        'source_column' => 'Global rate item',
        'source_key' => sha1('Global rate item'),
        'description' => 'Global rate item',
        'unit' => 'M2',
        'unit_price' => 20,
        'exchange_rate' => 3.2,
        'unit_price_ils' => 64,
        'quantity' => 2,
        'total_price' => 40,
        'total_price_ils' => 128,
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('damage-assessment-borrowers.index'))
        ->assertOk()
        ->assertSee('globalExchangeRateModal', false)
        ->assertSee('سعر الصرف الموحد');

    $this->actingAs($user)
        ->put(route('damage-assessment-borrowers.exchange-rate.update'), [
            'exchange_rate' => 3.7,
        ])
        ->assertRedirect(route('damage-assessment-borrowers.index'));

    expect((float) BorrowerPricingSetting::query()->sole()->exchange_rate)->toBe(3.7)
        ->and((float) $borrower->refresh()->exchange_rate)->toBe(3.7)
        ->and((float) $borrower->boq_total_ils)->toBe(148.0)
        ->and((float) $borrower->boqItems()->first()->unit_price_ils)->toBe(74.0)
        ->and((float) $borrower->boqItems()->first()->total_price_ils)->toBe(148.0)
        ->and((float) $catalogItem->refresh()->unit_price_ils)->toBe(74.0);
});

it('adds borrowers to the sidebar for database officers', function () {
    $role = Role::findOrCreate('Database Officer', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $module = Sidebar::forUser($user)->firstWhere('key', 'damage_assessment_borrowers');

    expect($module)->not->toBeNull()
        ->and($module['sections']->first()['url'])->toBe('damage-assessment-borrowers');
});

it('adds borrowers to the sidebar for borrower project officers', function () {
    $role = Role::findOrCreate('Project Officer - Borrowers', 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    $module = Sidebar::forUser($user)->firstWhere('key', 'damage_assessment_borrowers');

    expect($module)->not->toBeNull()
        ->and($module['sections']->first()['url'])->toBe('damage-assessment-borrowers');

    $this->actingAs($user)
        ->get(route('damage-assessment-borrowers.index'))
        ->assertOk();
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

it('calculates full demolition borrower damage value from area and floor type', function () {
    $path = tempnam(sys_get_temp_dir(), 'borrower-import-demolition-').'.json';
    file_put_contents($path, json_encode([
        'records' => [
            [
                'row_number' => 2,
                'source_uuid' => 'uuid-demolition-ground',
                'borrower_name' => 'Ground Demolition Borrower',
                'borrower_id_number' => '9912',
                'loan_unit_area' => 160,
                'loan_unit_floor_type_label' => 'ارضي',
                'loan_unit_damage_label' => 'هدم كلي',
            ],
            [
                'row_number' => 3,
                'source_uuid' => 'uuid-demolition-repeated',
                'borrower_name' => 'Repeated Demolition Borrower',
                'borrower_id_number' => '9913',
                'loan_unit_area' => 140,
                'loan_unit_floor_type_label' => 'متكرر',
                'loan_unit_damage_label' => 'هدم كلي',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    try {
        $summary = app(BorrowerSpreadsheetImportService::class)->import($path);
        $groundBorrower = DamageAssessmentBorrower::query()->where('source_uuid', 'uuid-demolition-ground')->sole();
        $repeatedBorrower = DamageAssessmentBorrower::query()->where('source_uuid', 'uuid-demolition-repeated')->sole();

        expect($summary['created'])->toBe(2)
            ->and((float) $groundBorrower->boq_total_usd)->toBe(52000.0)
            ->and($groundBorrower->loan_unit_floor_type)->toBe('ground')
            ->and((float) $repeatedBorrower->boq_total_usd)->toBe(39200.0)
            ->and($repeatedBorrower->loan_unit_floor_type)->toBe('repeated');
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

it('prefers the existing borrower identity over another source uuid match during import', function () {
    $identityMatch = DamageAssessmentBorrower::query()->create([
        'source_uuid' => 'identity-match-old-uuid',
        'borrower_name' => 'Identity Match Borrower',
        'borrower_id_number' => '8881',
        'is_borrower_alive' => true,
        'risk_level' => 'low',
        'risk_score' => 0,
    ]);

    $sourceUuidMatch = DamageAssessmentBorrower::query()->create([
        'source_uuid' => 'uuid-owned-by-existing-row',
        'borrower_name' => 'Source Uuid Borrower',
        'borrower_id_number' => '9991',
        'is_borrower_alive' => true,
        'risk_level' => 'low',
        'risk_score' => 0,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'borrower-import-source-conflict-').'.json';
    file_put_contents($path, json_encode([
        'records' => [
            [
                'row_number' => 2,
                'source_uuid' => 'uuid-owned-by-existing-row',
                'source_submission_id' => 20,
                'borrower_name' => 'Updated Source Uuid Borrower',
                'borrower_id_number' => '8881',
                'employment_status_label' => 'متقاعد',
                'alive_label' => 'نعم',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    try {
        $summary = app(BorrowerSpreadsheetImportService::class)->import($path);

        expect($summary['updated'])->toBe(1)
            ->and($identityMatch->refresh()->borrower_name)->toBe('Updated Source Uuid Borrower')
            ->and($identityMatch->source_uuid)->toBe('uuid-owned-by-existing-row')
            ->and($identityMatch->source_submission_id)->toBe(20)
            ->and($sourceUuidMatch->refresh()->source_uuid)->toBeNull()
            ->and($sourceUuidMatch->borrower_id_number)->toBe('9991');
    } finally {
        @unlink($path);
    }
});
