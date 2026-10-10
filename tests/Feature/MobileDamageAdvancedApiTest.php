<?php

use App\Models\AssessmentStatus;
use App\Models\AuditedBuilding;
use App\Models\BuildingStatusHistory;
use App\Models\User;
use App\services\ArcgisService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    foreach (['municipalitie', 'neighborhood', 'building_name', 'owner_name', 'owner_id', 'floor_nos'] as $column) {
        if (! Schema::hasColumn('audited_buildings', $column)) {
            Schema::table('audited_buildings', fn (Blueprint $table) => $table->text($column)->nullable());
        }
    }
    $this->mobileUser = User::factory()->create(['allowed_phase_numbers' => [1]]);
    $this->mobileUser->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->mobileToken = $this->mobileUser->createToken('fixture', ['mobile:read'])->plainTextToken;
    AuditedBuilding::query()->create(['objectid' => 701, 'globalid' => 'allowed', 'building_name' => 'مبنى الأمل', 'phase_number' => 1,
        'owner_name' => 'أحمد حسن', 'owner_id' => '900123456', 'municipalitie' => 'غزة', 'neighborhood' => 'النصر', 'floor_nos' => '4', 'building_damage_status' => 'fully_damaged', 'field_status' => 'Completed']);
    AuditedBuilding::query()->create(['objectid' => 702, 'globalid' => 'hidden', 'building_name' => 'مبنى مخفي', 'phase_number' => 2, 'owner_name' => 'أحمد حسن', 'municipalitie' => 'بلدية مخفية']);
    $this->recordId = (int) AuditedBuilding::query()->where('objectid', 701)->value('id');
    $this->hiddenId = (int) AuditedBuilding::query()->where('objectid', 702)->value('id');
    $this->base = '/api/v1/damage-assessment/buildings/'.$this->recordId;
});

it('provides phase-scoped filter choices and combines advanced filters', function (): void {
    $this->withToken($this->mobileToken)->getJson('/api/v1/damage-assessment/buildings/filters')
        ->assertOk()->assertJsonPath('data.municipalities', ['غزة'])->assertJsonMissing(['بلدية مخفية']);
    $this->getJson('/api/v1/damage-assessment/buildings?municipality='.urlencode('غزة').'&damage_status=fully_damaged&field_completion=completed')
        ->assertOk()->assertJsonPath('total', 1);
    $this->getJson('/api/v1/damage-assessment/buildings?damage_status=no_damage')->assertOk()->assertJsonPath('total', 0);
});

it('returns approved detail fields only to audit-authorized users', function (): void {
    $this->withToken($this->mobileToken)->getJson($this->base)->assertOk()
        ->assertJsonPath('data.capabilities.full_details', true)->assertJsonFragment(['key' => 'floor_nos', 'label' => 'عدد الطوابق', 'value' => '4']);
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Team Leader -INF', 'web'));
    app('auth')->forgetGuards();
    $this->withToken($user->createToken('fixture', ['mobile:read'])->plainTextToken)->getJson($this->base)->assertOk()
        ->assertJsonPath('data.capabilities.full_details', false)->assertJsonCount(0, 'data.details');
    $this->getJson($this->base.'/history')->assertForbidden();
    $this->getJson($this->base.'/attachments')->assertForbidden();
});

it('separates engineering and legal histories with bounded pagination', function (): void {
    $status = AssessmentStatus::query()->create(['name' => 'accepted_by_engineer', 'label_en' => 'Accepted', 'label_ar' => 'مقبول هندسيًا', 'stage' => 'engineer', 'order_step' => 1]);
    foreach (range(1, 21) as $index) {
        BuildingStatusHistory::query()->create(['building_id' => 701, 'type' => 'QC/QA Engineer', 'status_id' => $status->id, 'user_id' => $this->mobileUser->id, 'notes' => 'engineering-'.$index]);
    }
    BuildingStatusHistory::query()->create(['building_id' => 701, 'type' => 'Legal Auditor', 'status_id' => $status->id, 'notes' => 'legal-note']);
    $this->withToken($this->mobileToken)->getJson($this->base.'/history?track=engineering')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 21)->assertJsonPath('last_page', 2);
    $this->getJson($this->base.'/history?track=legal')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.notes', 'legal-note');
    $this->getJson($this->base.'/history?track=invalid')->assertUnprocessable();
    $this->getJson('/api/v1/damage-assessment/buildings/'.$this->hiddenId.'/history')->assertNotFound();
});

it('unifies citizen name and identity inquiry without exposing identity fields in results', function (): void {
    foreach (['أحمد حسن', '900123456'] as $term) {
        $this->withToken($this->mobileToken)->getJson('/api/v1/damage-assessment/citizens?search='.urlencode($term))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.sector', 'buildings')->assertJsonMissingPath('data.0.owner_id');
    }
    $this->getJson('/api/v1/damage-assessment/citizens?search=%25')->assertUnprocessable();
    $this->getJson('/api/v1/damage-assessment/citizens?search=++')->assertUnprocessable();
    $this->getJson('/api/v1/damage-assessment/citizens?search=%25%25')->assertOk()->assertJsonPath('total', 0);
});

it('denies citizen inquiry to users without audit access', function (): void {
    $user = User::factory()->create();
    $this->withToken($user->createToken('fixture', ['mobile:read'])->plainTextToken)->getJson('/api/v1/damage-assessment/citizens?search=fixture')->assertForbidden();
});

it('proxies authorized attachments without leaking provider secrets or urls', function (): void {
    $arcgis = Mockery::mock(ArcgisService::class);
    $arcgis->shouldReceive('getToken')->with(true)->twice()->andReturn('private-provider-token');
    $arcgis->shouldReceive('getLayerId')->twice()->with(App\Models\Building::class)->andReturn(0);
    $arcgis->shouldReceive('getAttachmentsResult')->twice()->with(701, 0, 'private-provider-token', true)->andReturn(['success' => true, 'attachments' => [['id' => 9, 'name' => 'photo.png', 'contentType' => 'image/png', 'size' => 8, 'url' => 'private-url']]]);
    $arcgis->shouldReceive('downloadAttachment')->once()->with(701, 0, 9, 'private-provider-token', true)->andReturn(['success' => true, 'body' => 'fixture!']);
    $this->instance(ArcgisService::class, $arcgis);
    $this->withToken($this->mobileToken)->getJson($this->base.'/attachments')->assertOk()->assertJsonPath('data.0.viewable', true)->assertDontSee('private-provider-token')->assertDontSee('private-url');
    $this->get($this->base.'/attachments/9', ['Accept' => 'application/json'])->assertOk()->assertContent('fixture!')->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('rejects attachment ids outside the scoped record before download', function (): void {
    $arcgis = Mockery::mock(ArcgisService::class);
    $arcgis->shouldReceive('getToken')->with(true)->once()->andReturn('fixture-token');
    $arcgis->shouldReceive('getLayerId')->once()->andReturn(0);
    $arcgis->shouldReceive('getAttachmentsResult')->once()->andReturn(['success' => true, 'attachments' => [['id' => 9, 'contentType' => 'image/png']]]);
    $arcgis->shouldNotReceive('downloadAttachment');
    $this->instance(ArcgisService::class, $arcgis);
    $this->withToken($this->mobileToken)->getJson($this->base.'/attachments/77')->assertNotFound();
    $this->getJson('/api/v1/damage-assessment/buildings/'.$this->hiddenId.'/attachments')->assertNotFound();
});

it('scopes housing citizen results and history through the allowed parent building', function (): void {
    foreach (['q_9_3_1_first_name', 'q_9_3_4_last_name', 'id_number1'] as $column) {
        if (! Schema::hasColumn('audited_housing_units', $column)) {
            Schema::table('audited_housing_units', fn (Blueprint $table) => $table->text($column)->nullable());
        }
    }
    foreach (['allowed', 'hidden'] as $index => $parent) {
        \App\Models\AuditedHousingUnit::query()->create(['objectid' => 801 + $index, 'globalid' => 'housing-'.$parent,
            'parentglobalid' => $parent, 'q_9_3_1_first_name' => 'أحمد', 'q_9_3_4_last_name' => 'حسن', 'id_number1' => '900123456']);
    }
    $id = (int) \App\Models\AuditedHousingUnit::query()->where('objectid', 801)->value('id');
    $hidden = (int) \App\Models\AuditedHousingUnit::query()->where('objectid', 802)->value('id');
    $status = AssessmentStatus::query()->create(['name' => 'accepted_by_lawyer', 'label_en' => 'Legal acceptance', 'label_ar' => 'مقبول قانونيًا', 'stage' => 'lawyer', 'order_step' => 1]);
    \App\Models\HousingStatusHistory::query()->create(['housing_id' => 801, 'type' => 'Legal Auditor', 'status_id' => $status->id, 'notes' => 'fixture legal history']);
    $this->withToken($this->mobileToken)->getJson('/api/v1/damage-assessment/citizens?search='.urlencode('أحمد حسن'))
        ->assertOk()->assertJsonPath('total', 2)->assertJsonPath('data.1.sector', 'housing-units');
    $this->getJson('/api/v1/damage-assessment/housing-units/'.$id.'/history?track=legal')->assertOk()->assertJsonPath('data.0.notes', 'fixture legal history');
    $this->getJson('/api/v1/damage-assessment/housing-units/'.$hidden.'/history?track=legal')->assertNotFound();
    $this->getJson('/api/v1/damage-assessment/citizens?search=123')->assertUnprocessable()->assertJsonValidationErrors('search');
});

it('refuses unsupported or oversized attachments before requesting their bytes', function (string $mime, int $size, int $status): void {
    $arcgis = Mockery::mock(ArcgisService::class);
    $arcgis->shouldReceive('getToken')->with(true)->once()->andReturn('fixture');
    $arcgis->shouldReceive('getLayerId')->once()->andReturn(0);
    $arcgis->shouldReceive('getAttachmentsResult')->once()->andReturn(['success' => true, 'attachments' => [['id' => 9, 'contentType' => $mime, 'size' => $size]]]);
    $arcgis->shouldNotReceive('downloadAttachment');
    $this->instance(ArcgisService::class, $arcgis);
    $this->withToken($this->mobileToken)->getJson($this->base.'/attachments/9')->assertStatus($status);
})->with([['text/html', 100, 415], ['application/pdf', 16 * 1024 * 1024, 413]]);

it('does not treat audit dashboard visibility as permission to read complete assessments', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Area Manager', 'web'));
    $this->withToken($user->createToken('fixture', ['mobile:read'])->plainTextToken)->getJson($this->base)
        ->assertOk()->assertJsonPath('data.capabilities.full_details', false)->assertJsonCount(0, 'data.details');
    $this->getJson('/api/v1/damage-assessment/citizens?search=fixture')->assertForbidden();
});
