<?php

use App\Models\MobileFormAttachment;
use App\Models\MobileFormSubmission;
use App\Models\User;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\HeksShelter\Models\ShelterCase;
use App\Modules\HeksShelter\Models\ShelterPayment;
use Database\Seeders\HeksShelterPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(HeksShelterPermissionsSeeder::class);
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    $this->officer = User::factory()->create();
    $this->officer->assignRole(Role::findOrCreate('Database Officer', 'web'));
});

function heks3Payload(): array
{
    return ['submission_uuid' => (string) Str::uuid(), 'form_version' => app(\App\services\MobileFormDefinitionService::class)->definitions()['heks-phase-three']['version'],
        'answers' => ShelterCase::factory()->make()->answers,
        'location' => ['latitude' => 31.5, 'longitude' => 34.45, 'accuracy' => 12]];
}

it('publishes a phase three workspace with filters and every operational tab', function (): void {
    $this->actingAs($this->officer);
    foreach (['overview', 'cases', 'map', 'budget', 'payments', 'visits', 'reports', 'mobile'] as $tab) {
        $this->get(route('heks3.index', ['tab' => $tab]))->assertOk()->assertSee('HEKS / PHASE 03')->assertSee('heks-shelter.css');
    }
    $gaza = ShelterCase::factory()->create(['name' => 'حالة غزة']);
    ShelterCase::factory()->create(['name' => 'حالة رفح', 'governorate' => 'rafah']);
    $this->get(route('heks3.index', ['tab' => 'cases', 'governorate' => 'gaza']))->assertSee($gaza->name)->assertDontSee('حالة رفح');
    $this->getJson(route('heks3.index', ['status' => 'invalid']))->assertUnprocessable();
});

it('scopes engineers to their own cases and rejects unauthorized reads and actions', function (): void {
    $engineer = User::factory()->create();
    $engineer->assignRole('HEKS Field Engineer');
    $own = ShelterCase::factory()->create(['engineer_id' => $engineer->id, 'name' => 'ملف المهندس']);
    $other = ShelterCase::factory()->create(['name' => 'ملف خاص بآخر']);
    $this->actingAs($engineer)->get(route('heks3.index'))->assertOk()->assertSee('ملف المهندس')->assertDontSee('ملف خاص بآخر');
    $this->getJson(route('heks3.show', $other))->assertNotFound();
    $this->postJson(route('heks3.act', $own), ['version' => 1, 'action' => 'approve', 'note' => 'اعتماد غير مخول'])->assertForbidden();
    $this->get(route('heks3.export'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('heks3.index'))->assertForbidden();
});

it('receives the complete mobile survey once with private attachments coordinates and audit history', function (): void {
    Storage::fake('local');
    $token = $this->officer->createToken('test', ['mobile:read', 'mobile:submit'])->plainTextToken;
    $this->withToken($token);
    $this->getJson('/api/v1/forms')->assertOk()->assertJsonFragment(['key' => 'heks-phase-three']);
    $schema = $this->getJson('/api/v1/forms/heks-phase-three/schema')->assertOk()->json('data');
    expect($schema['sections'])->toHaveCount(9);
    expect(collect($schema['sections'])->sum(fn (array $section): int => count($section['fields'])))->toBe(144);
    $payload = heks3Payload();
    $payload['answers']['identity_number'] = '٤٠٠١٢٣٤٥٦';
    $upload = $this->post('/api/v1/forms/heks-phase-three/attachments', ['submission_uuid' => $payload['submission_uuid'], 'file' => UploadedFile::fake()->image('shelter.jpg')], ['Accept' => 'application/json'])->assertCreated();
    $payload['attachments'] = [$upload->json('data.id')];
    $first = $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertCreated()->assertJsonPath('replayed', false);
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data.id', $first->json('data.id'));
    $case = ShelterCase::query()->sole();
    expect($case->phase)->toBe(3)->and($case->identity_number)->toBe('400123456')->and((float) $case->latitude)->toBe(31.5)->and($case->events()->count())->toBe(1);
    expect($case->submissions()->with('attachments')->first()->attachments)->toHaveCount(1);
    expect(MobileFormSubmission::query()->sole()->changes['after']['survey_revision_id'])->toBe($case->survey_revision_id);
    $this->assertDatabaseHas('heks_survey_answer_changes', ['case_id' => $case->id, 'field_path' => 'unit_area', 'new_value' => '90']);
    $this->getJson('/api/v1/forms/heks-phase-three/schema?record='.$case->id)->assertOk()->assertJsonPath('data.answers.unit_area', 90);
    $this->getJson('/api/v1/portal/heks-shelter/records')->assertOk()->assertJsonPath('total', 1);
    $this->getJson('/api/v1/portal/heks-shelter/records/'.$case->id)->assertOk()->assertJsonPath('data.title', $case->name);
    $payload['submission_uuid'] = (string) Str::uuid();
    $payload['attachments'] = [];
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertUnprocessable()->assertJsonValidationErrors('answers.identity_number');
    expect(ShelterCase::query()->count())->toBe(1);
});

it('validates survey consistency and rejects protected fields and stale mobile submissions', function (): void {
    $this->withToken($this->officer->createToken('test', ['mobile:read', 'mobile:submit'])->plainTextToken);
    $payload = heks3Payload();
    $payload['answers']['grant_amount'] = 50000;
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertUnprocessable();
    unset($payload['answers']['grant_amount']);
    $payload['answers']['eligibility'] = 'blocked';
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertUnprocessable()->assertJsonValidationErrors('answers.safety_risks');
    $payload['answers']['safety_risks'] = ['uxo'];
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertCreated();
    $case = ShelterCase::query()->sole();
    expect($case->status)->toBe('blocked');
    $schema = $this->getJson('/api/v1/forms/heks-phase-three/schema?record='.$case->id)->json('data');
    $payload['submission_uuid'] = (string) Str::uuid();
    $payload['record_id'] = $case->id;
    $payload['record_version'] = $schema['record_version'];
    $case->update(['version' => 2]);
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertConflict();
    $payload['record_version'] = $this->getJson('/api/v1/forms/heks-phase-three/schema?record='.$case->id)->json('data.record_version');
    $payload['answers']['disability_total'] = 20;
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertUnprocessable()->assertJsonValidationErrors('answers.disability_total');
});

it('enforces mobile roles and record ownership for phase three', function (): void {
    $engineer = User::factory()->create();
    $engineer->assignRole('HEKS Field Engineer');
    $this->withToken($engineer->createToken('test', ['mobile:read', 'mobile:submit'])->plainTextToken);
    $this->getJson('/api/v1/forms/heks-phase-three/schema')->assertOk();
    $other = ShelterCase::factory()->create();
    $this->getJson('/api/v1/forms/heks-phase-three/schema?record='.$other->id)->assertNotFound();
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', heks3Payload())->assertCreated();
    expect(ShelterCase::query()->where('engineer_id', $engineer->id)->count())->toBe(1);
    $engineer->removeRole('HEKS Field Engineer');
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/forms/heks-phase-three/schema')->assertForbidden();
});

it('requires safe eligible reviewed files with priced quantities and no legacy benefit', function (): void {
    $this->actingAs($this->officer);
    $case = ShelterCase::factory()->create();
    $action = ['version' => 1, 'action' => 'approve', 'note' => 'اعتماد نهائي'];
    $this->postJson(route('heks3.act', $case), $action)->assertUnprocessable();
    $case->update(['engineering_status' => 'approved', 'social_status' => 'approved', 'grant_amount' => 1000]);
    $legacy = HeksBeneficiary::query()->create(['code' => 'LEGACY-1', 'identity_number' => $case->identity_number, 'is_selected' => true]);
    $this->postJson(route('heks3.act', $case), $action)->assertUnprocessable();
    $this->getJson(route('heks3.show', $case))->assertOk()->assertSee('HEKS');
    $legacy->update(['is_selected' => false]);
    $case->update(['answers' => [...$case->answers, 'uxo' => 'yes']]);
    $this->postJson(route('heks3.act', $case), $action)->assertUnprocessable();
    $case->update(['answers' => [...$case->answers, 'uxo' => 'no']]);
    $this->postJson(route('heks3.act', $case), $action)->assertOk();
    expect($case->fresh()->status)->toBe('approved')->and($case->fresh()->approved_at)->not->toBeNull();
    expect($legacy->fresh()->is_selected)->toBeFalse();
    $this->postJson(route('heks3.act', $case), $action)->assertConflict();
});

it('calculates the boq and freezes it after approval', function (): void {
    $case = ShelterCase::factory()->create();
    $this->actingAs($this->officer);
    $payload = ['version' => 1, 'action' => 'boq', 'note' => 'إعداد كميات الإصلاح', 'lines' => [['description' => 'إصلاح باب', 'unit' => 'عدد', 'quantity' => 2, 'unit_price' => 100.25]]];
    $this->postJson(route('heks3.act', $case), $payload)->assertOk();
    expect((float) $case->fresh()->grant_amount)->toBe(200.5)->and($case->boqLines()->count())->toBe(1);
    $case->update(['status' => 'approved', 'approved_at' => now()]);
    $payload['version'] = 2;
    $this->postJson(route('heks3.act', $case), $payload)->assertUnprocessable();
});

it('records bounded payments once and prevents cancellation after the first disbursement', function (): void {
    $case = ShelterCase::factory()->approved()->create();
    $this->actingAs($this->officer);
    $payload = ['version' => 1, 'action' => 'payment', 'note' => 'دفعة أولى موثقة', 'amount' => 400, 'paid_on' => today()->toDateString(), 'reference' => 'PAY-1'];
    $this->postJson(route('heks3.act', $case), $payload)->assertOk();
    expect($case->fresh()->benefited_at)->not->toBeNull()->and($case->fresh()->status)->toBe('in_progress');
    $this->postJson(route('heks3.act', $case), $payload)->assertConflict();
    $payload['version'] = 2;
    $this->postJson(route('heks3.act', $case), $payload)->assertUnprocessable();
    $payload['reference'] = 'PAY-2';
    $payload['amount'] = 601;
    $this->postJson(route('heks3.act', $case), $payload)->assertUnprocessable();
    $this->postJson(route('heks3.act', $case), ['version' => 2, 'action' => 'reopen', 'note' => 'طلب إلغاء بعد الصرف'])->assertUnprocessable();
    expect(ShelterPayment::query()->count())->toBe(1)->and((float) $case->payments()->sum('amount'))->toBe(400.0);
});

it('completes only after final field verification and full financial settlement', function (): void {
    $case = ShelterCase::factory()->approved()->create(['status' => 'in_progress', 'benefited_at' => now()]);
    $this->actingAs($this->officer);
    $this->postJson(route('heks3.act', $case), ['version' => 1, 'action' => 'complete', 'note' => 'طلب إغلاق الملف'])->assertUnprocessable();
    $this->postJson(route('heks3.act', $case), ['version' => 1, 'action' => 'visit', 'note' => 'انتهاء أعمال الإصلاح', 'completion' => 100, 'visited_on' => today()->toDateString()])->assertOk();
    $this->postJson(route('heks3.act', $case), ['version' => 2, 'action' => 'complete', 'note' => 'طلب إغلاق الملف'])->assertUnprocessable();
    $this->postJson(route('heks3.act', $case), ['version' => 2, 'action' => 'payment', 'note' => 'تسوية نهائية', 'amount' => 1000, 'paid_on' => today()->toDateString(), 'reference' => 'FINAL'])->assertOk();
    $this->postJson(route('heks3.act', $case), ['version' => 3, 'action' => 'complete', 'note' => 'تمت التسوية والإنجاز'])->assertOk();
    expect($case->fresh()->status)->toBe('completed');
});

it('exports filtered typed excel data and a readable Arabic pdf', function (): void {
    $case = ShelterCase::factory()->create(['name' => '=FORMULA', 'governorate' => 'gaza']);
    ShelterCase::factory()->create(['governorate' => 'rafah']);
    $this->actingAs($this->officer);
    $binary = $this->get(route('heks3.export', ['governorate' => 'gaza']))->assertOk()->streamedContent();
    $path = tempnam(sys_get_temp_dir(), 'heks-xlsx');
    file_put_contents($path, $binary);
    try {
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        expect($sheet->getHighestRow())->toBe(2)->and($sheet->getCell('A2')->getDataType())->toBe('s');
    } finally {
        unlink($path);
    }
    $pdf = $this->get(route('heks3.pdf', $case))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
    expect($pdf)->toStartWith('%PDF')->toContain('DejaVuSans');
    file_put_contents(sys_get_temp_dir().'/heks3-review.pdf', $pdf);
});

it('resets both reviews on a mobile correction and refuses changes after approval', function (): void {
    $case = ShelterCase::factory()->reviewed()->create(['engineer_id' => $this->officer->id]);
    $this->withToken($this->officer->createToken('test', ['mobile:read', 'mobile:submit'])->plainTextToken);
    $schema = $this->getJson('/api/v1/forms/heks-phase-three/schema?record='.$case->id)->assertOk()->json('data');
    $payload = ['submission_uuid' => (string) Str::uuid(), 'form_version' => $schema['version'], 'record_id' => $case->id, 'record_version' => $schema['record_version'], 'answers' => [...$schema['answers'], 'engineer_notes' => 'تصحيح بعد الزيارة']];
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertCreated();
    expect($case->fresh()->engineering_status)->toBe('pending')->and($case->fresh()->social_status)->toBe('pending');
    $case->update(['status' => 'approved', 'approved_at' => now()]);
    $payload['record_version'] = $this->getJson('/api/v1/forms/heks-phase-three/schema?record='.$case->id)->json('data.record_version');
    $payload['submission_uuid'] = (string) Str::uuid();
    $payload['answers']['engineer_notes'] = 'تعديل محظور';
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertUnprocessable();
    expect($case->fresh()->answers['engineer_notes'])->toBe('تصحيح بعد الزيارة');
});

it('rejects reused application codes and inconsistent family breakdowns', function (): void {
    $this->withToken($this->officer->createToken('test', ['mobile:read', 'mobile:submit'])->plainTextToken);
    ShelterCase::factory()->create(['code' => 'APPLICATION-1']);
    $payload = heks3Payload();
    $payload['answers']['code'] = 'APPLICATION-1';
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertUnprocessable()->assertJsonValidationErrors('answers.code');
    unset($payload['answers']['code']);
    foreach (['under8', '8_17', '18_59', '60_plus'] as $age) {
        foreach (['male', 'female'] as $gender) {
            $payload['answers']['family_'.$age.'_'.$gender] = 1;
        }
    }
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertUnprocessable()->assertJsonValidationErrors('answers.family_total');
    expect(ShelterCase::query()->count())->toBe(1);
});

it('never serves an attachment belonging to another file', function (): void {
    Storage::fake('local');
    $case = ShelterCase::factory()->create();
    $attachment = MobileFormAttachment::factory()->create();
    $this->actingAs($this->officer)->get(route('heks3.attachment', [$case, $attachment]))->assertNotFound();
});
