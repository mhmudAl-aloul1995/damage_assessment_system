<?php

use App\Jobs\ExportAttachmentsJob;
use App\Jobs\ExportDataJob;
use App\Models\Building;
use App\Models\Export;
use App\Models\Filter;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

it('shows export page actions including objectid import', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response->assertOk();
    $response->assertSee('value="housing_units_count"', false);
    $response->assertSee('value="latitude"', false);
    $response->assertSee('value="longitude"', false);
    $response->assertSee(__('ui.exports.import_objectids'));
    $response->assertSee('name="building_end_from"', false);
    $response->assertSee('name="building_end_to"', false);
    $response->assertSee('name="include_legal_notes"', false);
    $response->assertSee('name="include_engineering_notes"', false);
    $response->assertSee('name="legal_notes_filter"', false);
    $response->assertSee('name="engineering_notes_filter"', false);
    $response->assertSee('name="update_housing_names_from_civil_registry"', false);
    $response->assertSee('تحديث أسماء المالك/الزوجات من السجل المدني قبل التصدير');
});

it('shows real database columns even when they are not assessment fields', function () {
    Schema::table('buildings', function (Blueprint $table): void {
        $table->string('example_real_database_column')->nullable();
    });

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response->assertOk();
    $response->assertSee('value="example_real_database_column"', false);
    $response->assertSee('Other');
});

it('shows select controls for each export field group', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response->assertOk();
    $response->assertSee('class="mb-5 column-group"', false);
    $response->assertSee("toggleColumnGroup(this,'building_columns[]',true)", false);
    $response->assertSee("toggleColumnGroup(this,'building_columns[]',false)", false);
    $response->assertSee("toggleColumnGroup(this,'housing_columns[]',true)", false);
    $response->assertSee("toggleColumnGroup(this,'housing_columns[]',false)", false);
});

it('shows a selected export columns status button', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response->assertOk();
    $response->assertSee('id="selectedColumnsStatusBtn"', false);
    $response->assertSee('id="selectedColumnsCount"', false);
    $response->assertSee('data-export-column-checkbox', false);
    $response->assertSee('function updateSelectedColumnsStatus()', false);
});

it('shows a cancel button for the active export download', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response->assertOk();
    $response->assertSee('id="cancelCurrentExportBtn"', false);
    $response->assertSee('let activeExportId = null;', false);
    $response->assertSee('activeExportId + "/cancel"', false);
});

it('shows processed rows out of total rows during export progress', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response->assertOk();
    $response->assertSee('function updateProgress(progress, processed, totalRows = null)', false);
    $response->assertSee('updateProgress(response.progress, response.processed, response.total_rows)', false);
    $response->assertSee('تم تجهيز ', false);
});

it('cancels a pending export download', function () {
    $user = User::factory()->create();

    $export = Export::query()->create([
        'status' => 'pending',
        'filters' => json_encode([
            'export_type' => 'excel',
            'building_columns' => ['objectid'],
        ], JSON_UNESCAPED_UNICODE),
        'user_id' => $user->id,
        'progress' => 0,
        'processed' => 0,
        'file_name' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->post("damage-assessment/exports/{$export->id}/cancel");

    $response
        ->assertOk()
        ->assertJsonPath('status', true);

    expect($export->fresh()->status)->toBe('cancelled');
});

it('shows the assessment obstacle export filter with a readable label', function () {
    Filter::query()->where('list_name', 'assessment_obstacle')->delete();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response->assertOk();
    $response->assertSee('Assessment Obstacle', false);
    $response->assertSee('name="filters[assessment_obstacle][]"', false);
    $response->assertSee('value="yes"', false);
    $response->assertSee('value="no"', false);
});

it('shows attachment export controls on the export page', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response->assertOk();
    $response->assertSee('value="attachments"', false);
    $response->assertSee('value="data_with_attachments"', false);
    $response->assertSee('name="attachment_sources[]"', false);
    $response->assertSee('name="attachment_type_filters[]"', false);
    $response->assertSee('name="include_attachment_excel_columns"', false);
    $response->assertSee('name="attachment_excel_display"', false);
    $response->assertSee('value="links"', false);
    $response->assertSee('value="images"', false);
    $response->assertSee('value="building_arcgis"', false);
    $response->assertSee('value="housing_unit_arcgis"', false);
    $response->assertSee('value="damage_photos"', false);
    $response->assertSee('data-type="zip"', false);
});

it('rejects data exports when no data columns are selected', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('export.start'), [
            'export_mode' => 'data',
            'export_type' => 'excel',
            'building_columns' => [],
            'housing_columns' => [],
        ]);

    $response
        ->assertStatus(422)
        ->assertJson([
            'status' => false,
            'message' => 'يرجى اختيار عمود واحد على الأقل من أعمدة البيانات قبل التصدير.',
        ]);

    expect(Export::query()->count())->toBe(0);
});

it('allows attachment only exports without selecting data columns', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('export.start'), [
            'export_mode' => 'attachments',
            'export_type' => 'zip',
            'building_columns' => [],
            'housing_columns' => [],
            'attachment_sources' => ['building_arcgis'],
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'status' => true,
        ]);

    expect(Export::query()->count())->toBe(1);
});

it('allows data exports with audit notes only', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('export.start'), [
            'export_mode' => 'data',
            'export_type' => 'excel',
            'building_columns' => [],
            'housing_columns' => [],
            'include_legal_notes' => '1',
            'legal_notes_filter' => 'with_notes',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'status' => true,
        ]);

    $export = Export::query()->firstOrFail();
    $filters = json_decode((string) $export->filters, true);

    expect($filters['include_legal_notes'])->toBe('1');
    expect($filters['legal_notes_filter'])->toBe('with_notes');

    Queue::assertPushed(ExportDataJob::class);
});

it('treats image attachment display as a request for excel attachment columns', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('export.start'), [
            'export_mode' => 'data',
            'export_type' => 'excel',
            'building_columns' => ['objectid'],
            'attachment_sources' => ['building_arcgis'],
            'attachment_type_filters' => ['images'],
            'attachment_excel_display' => 'images',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'status' => true,
        ]);

    $export = Export::query()->firstOrFail();
    $filters = json_decode((string) $export->filters, true);

    expect($filters['include_attachment_excel_columns'])->toBe('1');

    Queue::assertPushed(ExportAttachmentsJob::class);
    Queue::assertNotPushed(ExportDataJob::class);
});

it('treats data with attachments mode as a request for excel attachment columns', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('export.start'), [
            'export_mode' => 'data_with_attachments',
            'export_type' => 'excel',
            'building_columns' => ['objectid'],
            'attachment_sources' => ['housing_unit_arcgis'],
            'attachment_type_filters' => ['identity'],
            'attachment_excel_display' => 'links',
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'status' => true,
        ]);

    $export = Export::query()->firstOrFail();
    $filters = json_decode((string) $export->filters, true);

    expect($filters['include_attachment_excel_columns'])->toBe('1');
    expect($filters['export_type'])->toBe('zip');

    Queue::assertPushed(ExportAttachmentsJob::class);
    Queue::assertNotPushed(ExportDataJob::class);
});

it('forces attachment export modes to zip in the browser payload', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response->assertOk();
    $response->assertSee("['attachments', 'data_with_attachments'].includes(selectedExportMode())", false);
    $response->assertSee("exportType = 'zip';", false);
});

it('treats zip exports as attachment exports when the browser leaves data mode selected', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson(route('export.start'), [
            'export_mode' => 'data',
            'export_type' => 'zip',
            'building_columns' => ['objectid'],
            'attachment_sources' => ['housing_unit_arcgis'],
            'attachment_type_filters' => ['all'],
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'status' => true,
        ]);

    $export = Export::query()->firstOrFail();
    $filters = json_decode((string) $export->filters, true);

    expect($filters['export_mode'])->toBe('attachments');
    expect($filters['export_type'])->toBe('zip');

    Queue::assertPushed(ExportAttachmentsJob::class);
    Queue::assertNotPushed(ExportDataJob::class);
});

it('sends the selected export mode explicitly in the browser payload', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response
        ->assertOk()
        ->assertSee("replaceFormField(formData, 'export_mode', exportMode);", false)
        ->assertSee("if (exportMode === 'data_with_attachments')", false)
        ->assertSee("replaceFormField(formData, 'include_attachment_excel_columns', '1');", false);
});

it('fills the neighborhood filter from unique building neighborhoods', function () {
    Filter::query()->create([
        'list_name' => 'neighborhood',
        'name' => 'FromFiltersTable',
        'label' => 'From Filters Table',
    ]);

    Building::query()->create([
        'objectid' => 1001,
        'globalid' => 'building-1001',
        'neighborhood' => 'Rimal',
    ]);

    Building::query()->create([
        'objectid' => 1002,
        'globalid' => 'building-1002',
        'neighborhood' => 'Rimal',
    ]);

    Building::query()->create([
        'objectid' => 1003,
        'globalid' => 'building-1003',
        'neighborhood' => 'Zeitoun',
    ]);

    Building::query()->create([
        'objectid' => 1004,
        'globalid' => 'building-1004',
        'neighborhood' => null,
    ]);

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response->assertOk();
    $response->assertSee('name="filters[neighborhood][]"', false);
    $response->assertSee('value="Rimal"', false);
    $response->assertSee('value="Zeitoun"', false);
    $response->assertDontSee('value="FromFiltersTable"', false);

    expect(substr_count($response->getContent(), 'value="Rimal"'))->toBe(1);
});
