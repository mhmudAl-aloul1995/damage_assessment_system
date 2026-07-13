<?php

use App\Jobs\SyncHeksKoboSubmission;
use App\Models\KoboRestSubmission;
use App\Modules\Heks\Services\HeksKoboMappingReportService;
use App\Modules\Heks\Services\HeksValueNormalizer;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

test('heks value normalizer handles digits codes money dates booleans and uuid values', function () {
    $normalizer = app(HeksValueNormalizer::class);

    expect($normalizer->digits('۱۲٣٤'))->toBe('1234')
        ->and($normalizer->code('00123.0'))->toBe('00123')
        ->and($normalizer->code(' GDQ 3 '))->toBe('GDQ3')
        ->and($normalizer->money('₪ ١,٢٣٤.50'))->toBe(1234.5)
        ->and($normalizer->date('2026-07-12'))->toBe('2026-07-12')
        ->and($normalizer->boolean('نعم'))->toBeTrue()
        ->and($normalizer->boolean('0'))->toBeFalse()
        ->and($normalizer->uuid('uuid:abc-123'))->toBe('uuid:abc-123');
});

test('heks webhook queues submission on heks queue', function () {
    Queue::fake();
    config(['services.kobotoolbox.rest_service_token' => 'secret']);

    $this
        ->withHeader('X-Kobo-Token', 'secret')
        ->postJson('/api/heks/kobo-webhook/heks_main', [
            '_uuid' => 'uuid:webhook-001',
            'code' => 'WEB1',
            'beneficiary_name' => 'Webhook Beneficiary',
        ])
        ->assertAccepted()
        ->assertJsonPath('sync_status', 'queued');

    $submission = KoboRestSubmission::query()->where('submission_uuid', 'uuid:webhook-001')->sole();

    expect($submission->service_name)->toBe('heks_main')
        ->and($submission->sync_status)->toBe('queued');

    Queue::assertPushedOn('heks', SyncHeksKoboSubmission::class);
});

test('kobo rest submissions allow same uuid for different services', function () {
    KoboRestSubmission::query()->create([
        'service_name' => 'heks-main',
        'submission_uuid' => 'uuid:same-across-services',
        'payload' => ['_uuid' => 'uuid:same-across-services'],
        'received_at' => now(),
    ]);

    KoboRestSubmission::query()->create([
        'service_name' => 'heks-followups',
        'submission_uuid' => 'uuid:same-across-services',
        'payload' => ['_uuid' => 'uuid:same-across-services'],
        'received_at' => now(),
    ]);

    expect(KoboRestSubmission::query()->where('submission_uuid', 'uuid:same-across-services')->count())->toBe(2);
});

test('heks mapping report is generated from paired false and label workbooks', function () {
    $directory = storage_path('framework/testing/heks-mapping-report');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $technicalPath = $directory.'/false.xlsx';
    $labelsPath = $directory.'/labels.xlsx';
    $outputDirectory = $directory.'/reports';

    $technical = new Spreadsheet;
    $technical->getActiveSheet()
        ->setTitle('Heks Final V1')
        ->fromArray([
            ['application_code', 'family_info/head_name', 'boq_qty_3_1'],
            ['DGN1', 'Report Beneficiary', '2'],
        ]);
    (new Xlsx($technical))->save($technicalPath);
    $technical->disconnectWorksheets();

    $labels = new Spreadsheet;
    $labels->getActiveSheet()
        ->setTitle('Heks Final V1')
        ->fromArray([
            ['رقم الطلب/الكود', 'اسم رب الأسرة', 'كمية بند 3.1'],
            ['DGN1', 'Report Beneficiary', '2'],
        ]);
    (new Xlsx($labels))->save($labelsPath);
    $labels->disconnectWorksheets();

    $result = app(HeksKoboMappingReportService::class)->generate([
        'heks_main' => [
            'technical' => $technicalPath,
            'labels' => $labelsPath,
        ],
    ], $outputDirectory);

    expect(is_file($result['mapping_report']))->toBeTrue()
        ->and(is_file($result['boq_report']))->toBeTrue()
        ->and($result['rows'])->toBe(3)
        ->and($result['boq_rows'])->toBe(1);

    $mappingWorkbook = IOFactory::load($result['mapping_report']);
    $boqWorkbook = IOFactory::load($result['boq_report']);

    expect($mappingWorkbook->getActiveSheet()->getCell('C2')->getValue())->toBe('application_code')
        ->and($boqWorkbook->getActiveSheet()->getCell('C2')->getValue())->toBe('boq_qty_3_1');

    $mappingWorkbook->disconnectWorksheets();
    $boqWorkbook->disconnectWorksheets();
});
