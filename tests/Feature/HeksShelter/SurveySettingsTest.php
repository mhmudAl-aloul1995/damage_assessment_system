<?php

use App\Models\User;
use App\Modules\HeksShelter\Models\ShelterCase;
use App\Modules\HeksShelter\Models\SurveyRevision;
use App\Modules\HeksShelter\Services\SurveyDesigner;
use App\services\MobileFormDefinitionService;
use Database\Seeders\HeksShelterPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(HeksShelterPermissionsSeeder::class);
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    $this->manager = User::factory()->create();
    $this->manager->assignRole(Role::findOrCreate('Database Officer', 'web'));
    $this->actingAs($this->manager);
});

function settingsQuestion(SurveyRevision $draft, array $extra = []): array
{
    return [...['revision_id' => $draft->id, 'edit_version' => $draft->fresh()->edit_version, 'key' => 'water_leak', 'label' => 'هل يوجد تسرب مياه؟', 'type' => 'select', 'section' => 'أسئلة إضافية', 'section_order' => 100, 'position' => 0, 'enabled' => true, 'required' => false, 'choices_text' => "yes|نعم\nno|لا"], ...$extra];
}

it('edits a draft without changing the mobile definition and publishes typed storage', function (): void {
    $before = app(MobileFormDefinitionService::class)->definitions()['heks-phase-three']['version'];
    $this->get(route('heks3.settings'))->assertOk()->assertSee('إعدادات استبيان المرحلة الثالثة');
    $this->post(route('heks3.settings.draft'))->assertRedirect();
    $draft = SurveyRevision::query()->where('status', 'draft')->sole();
    $this->post(route('heks3.settings.save'), settingsQuestion($draft))->assertSessionHasNoErrors()->assertRedirect();
    expect(app(MobileFormDefinitionService::class)->definitions()['heks-phase-three']['version'])->toBe($before);
    expect(Schema::hasColumn('heks_shelter_cases', 'sf_water_leak'))->toBeFalse();
    $this->get(route('heks3.settings'))->assertOk()->assertSee('هل يوجد تسرب مياه؟');
    $this->post(route('heks3.settings.publish'), ['revision_id' => $draft->id, 'edit_version' => $draft->fresh()->edit_version])->assertSessionHasNoErrors()->assertRedirect();
    expect(Schema::hasColumn('heks_shelter_cases', 'sf_water_leak'))->toBeTrue()->and(Schema::hasColumn('heks_shelter_cases', 'answers'))->toBeFalse();
    expect(app(MobileFormDefinitionService::class)->definitions()['heks-phase-three']['version'])->not->toBe($before);
    $case = ShelterCase::factory()->create(['answers' => [...ShelterCase::factory()->make()->answers, 'water_leak' => 'yes']]);
    expect($case->fresh()->sf_water_leak)->toBe('yes')->and($case->fresh()->answers['water_leak'])->toBe('yes');
    $this->get(route('heks3.index', ['survey_filters' => [['field' => 'water_leak', 'operator' => 'eq', 'value' => 'yes']]]))->assertOk()->assertSee($case->name);
    $this->get(route('heks3.index', ['survey_filters' => [['field' => 'water_leak', 'operator' => 'eq', 'value' => 'no']]]))->assertOk()->assertDontSee($case->name);
});

it('stores selection rows and demographic numbers without an answers json column', function (): void {
    $case = ShelterCase::factory()->create();
    expect($case->sf_family_total)->toBe(4)->and($case->answers['recommendations'])->toBe(['repair']);
    $this->assertDatabaseHas('heks_survey_selections', ['case_id' => $case->id, 'field_key' => 'recommendations', 'value' => 'repair']);
    $this->get(route('heks3.index', ['survey_filters' => [['field' => 'family_total', 'operator' => 'gte', 'value' => '4'], ['field' => 'recommendations', 'operator' => 'eq', 'value' => 'repair']]]))->assertOk()->assertSee($case->name);
    $this->getJson(route('heks3.index', ['survey_filters' => [['field' => 'name;drop table', 'operator' => 'eq', 'value' => 'x']]]))->assertUnprocessable();
});

it('protects permissions published types keys and concurrent edits', function (): void {
    $draft = app(SurveyDesigner::class)->draft($this->manager);
    $this->postJson(route('heks3.settings.save'), settingsQuestion($draft, ['edit_version' => 0]))->assertUnprocessable();
    $field = $draft->questions()->where('key', 'identity_number')->firstOrFail();
    $this->post(route('heks3.settings.save'), settingsQuestion($draft, ['question_id' => $field->id, 'key' => 'identity_number', 'type' => 'integer']))->assertSessionHasErrors('key');
    $this->post(route('heks3.settings.save'), settingsQuestion($draft, ['question_id' => $field->id, 'key' => 'identity_number', 'type' => 'text', 'enabled' => false]))->assertSessionHasErrors('enabled');
    $this->post(route('heks3.settings.save'), settingsQuestion($draft))->assertSessionHasNoErrors();
    $this->postJson(route('heks3.settings.publish'), ['revision_id' => $draft->id, 'edit_version' => 1])->assertConflict();
    $reader = User::factory()->create();
    $reader->assignRole('HEKS Field Engineer');
    $this->actingAs($reader)->get(route('heks3.settings'))->assertForbidden();
    $this->postJson(route('heks3.settings.draft'))->assertForbidden();
});

it('saves repeat rows in a related typed table and filters child answers', function (): void {
    $draft = app(SurveyDesigner::class)->draft($this->manager);
    $group = 'residents_'.str_repeat('a', 38);
    $this->post(route('heks3.settings.save'), settingsQuestion($draft, ['key' => $group, 'label' => 'أسر مقيمة', 'type' => 'repeat', 'choices_text' => '']))->assertSessionHasNoErrors();
    $this->post(route('heks3.settings.save'), settingsQuestion($draft, ['key' => 'members', 'label' => 'عدد أفراد الأسرة', 'type' => 'integer', 'parent_key' => $group, 'choices_text' => '']))->assertSessionHasNoErrors();
    $this->post(route('heks3.settings.save'), settingsQuestion($draft, ['key' => 'name', 'label' => 'اسم فرد الأسرة', 'type' => 'text', 'parent_key' => $group, 'choices_text' => '']))->assertSessionHasNoErrors();
    $this->post(route('heks3.settings.publish'), ['revision_id' => $draft->id, 'edit_version' => $draft->fresh()->edit_version])->assertSessionHasNoErrors();
    $rows = [['members' => 6, 'name' => 'أحمد'], ['members' => 2, 'name' => 'علي']];
    $case = ShelterCase::factory()->create(['answers' => [...ShelterCase::factory()->make()->answers, $group => $rows]]);
    expect($case->fresh()->answers[$group])->toBe($rows);
    $this->assertDatabaseHas('heks_rows_'.$group, ['case_id' => $case->id, 'sf_members' => 6]);
    $this->get(route('heks3.index', ['survey_filters' => [['field' => $group.'.members', 'operator' => 'gte', 'value' => '5']]]))->assertOk()->assertSee($case->name);
});

it('keeps old labels and options on existing case pdfs while the mobile uses a new revision', function (): void {
    $case = ShelterCase::factory()->create();
    $oldRevision = $case->survey_revision_id;
    $draft = app(SurveyDesigner::class)->draft($this->manager);
    $question = $draft->questions()->where('key', 'damage')->firstOrFail();
    $this->post(route('heks3.settings.save'), settingsQuestion($draft, ['question_id' => $question->id, 'key' => 'damage', 'label' => 'تصنيف الضرر المعدل', 'section' => $question->section, 'type' => 'select', 'choices_text' => "none|لا يوجد ضرر\nminor|جزئي طفيف\nmoderate|متوسط معدل\nsevere|جزئي بليغ"]))->assertSessionHasNoErrors();
    $this->post(route('heks3.settings.publish'), ['revision_id' => $draft->id, 'edit_version' => $draft->fresh()->edit_version])->assertSessionHasNoErrors();
    expect($case->fresh()->survey_revision_id)->toBe($oldRevision);
    $html = $this->getJson(route('heks3.show', $case))->assertOk()->json('html');
    expect($html)->toContain('حالة الضرر')->not->toContain('تصنيف الضرر المعدل');
    $definition = app(MobileFormDefinitionService::class)->definitions()['heks-phase-three'];
    expect(collect($definition['sections'])->flatMap(fn ($section) => $section['fields'])->firstWhere('key', 'damage')['label'])->toBe('تصنيف الضرر المعدل');
});

it('delivers custom Arabic validation and conditional fields through the mobile api', function (): void {
    $draft = app(SurveyDesigner::class)->draft($this->manager);
    $this->post(route('heks3.settings.save'), settingsQuestion($draft, ['key' => 'roof_age', 'type' => 'integer', 'label' => 'عمر السقف', 'choices_text' => '', 'required' => true, 'minimum' => 1, 'maximum' => 40, 'error_message' => 'أدخل عمر السقف بين 1 و40', 'visible_field' => 'eligibility', 'visible_value' => 'safe']))->assertSessionHasNoErrors();
    $this->post(route('heks3.settings.publish'), ['revision_id' => $draft->id, 'edit_version' => $draft->fresh()->edit_version])->assertSessionHasNoErrors();
    $this->withToken($this->manager->createToken('test', ['mobile:read', 'mobile:submit'])->plainTextToken);
    $this->app['auth']->forgetGuards();
    $schema = $this->getJson('/api/v1/forms/heks-phase-three/schema')->assertOk()->json('data');
    $payload = ['submission_uuid' => (string) Str::uuid(), 'form_version' => $schema['version'], 'answers' => [...ShelterCase::factory()->make()->answers, 'roof_age' => 80]];
    $failure = $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertUnprocessable()->assertJsonValidationErrors('answers.roof_age');
    expect($failure->json('errors')['answers.roof_age'][0])->toBe('أدخل عمر السقف بين 1 و40');
    $payload['answers']['roof_age'] = 20;
    $this->postJson('/api/v1/forms/heks-phase-three/submissions', $payload)->assertCreated();
    $this->assertDatabaseHas('heks_shelter_cases', ['sf_roof_age' => 20]);
});
