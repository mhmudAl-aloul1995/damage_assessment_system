<?php

use App\Models\Building;
use App\Models\CommitteeDecision;
use App\Models\CommitteeMember;
use App\Models\HousingUnit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('database.connections.mysql', config('database.connections.sqlite'));
    DB::purge('mysql');
    Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
    app(RolesAndPermissionsSeeder::class)->run();
});

it('shows committee decision pages and datatable data for buildings and housing units', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view committee decisions');

    $building = Building::query()->create([
        'objectid' => 9001,
        'globalid' => 'building-guid-1',
        'building_name' => 'برج الأمل',
        'neighborhood' => 'الرمال',
        'assignedto' => 'Engineer Field',
        'building_damage_status' => 'committee_review',
    ]);

    HousingUnit::query()->create([
        'objectid' => 9101,
        'globalid' => 'housing-guid-1',
        'parentglobalid' => 'building-guid-1',
        'housing_unit_number' => 'A-12',
        'q_9_3_1_first_name' => 'أحمد',
        'q_9_3_2_second_name__father' => 'محمد',
        'q_9_3_4_last_name' => 'الحداد',
        'neighborhood' => 'الرمال',
        'unit_damage_status' => 'committee_review2',
    ]);

    $this->actingAs($user)
        ->get(route('committee-decisions.index'))
        ->assertOk()
        ->assertSee('قرارات اللجنة')
        ->assertSee('المباني')
        ->assertSee('الوحدات السكنية');

    $this->actingAs($user)
        ->get(route('committee-decisions.buildings.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertJsonFragment(['building_name' => 'برج الأمل']);

    $this->actingAs($user)
        ->get(route('committee-decisions.housing-units.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertSee('A-12');

    $this->actingAs($user)
        ->get(route('committee-decisions.buildings.show', $building))
        ->assertOk()
        ->assertSee('ملخص القرار');
});

it('completes the committee workflow, sends a direct telegram message, and syncs arcgis after required signatures', function () {
    config()->set('services.committee_decisions.telegram.bot_token', 'telegram-bot-token');
    config()->set('services.committee_decisions.telegram.chat_id', '');
    config()->set('services.committee_decisions.arcgis.base_url', 'https://example.test/arcgis/FeatureServer');
    config()->set('services.committee_decisions.arcgis.building_layer_id', 0);
    config()->set('services.committee_decisions.arcgis.identifier_field', 'objectid');
    config()->set('services.committee_decisions.arcgis.status_field', 'field_status');
    config()->set('services.committee_decisions.arcgis.status_value', 'not_completed');
    config()->set('services.committee_decisions.arcgis.token', 'static-token');

    Http::fake([
        'https://api.telegram.org/bottelegram-bot-token/sendMessage' => Http::response(['ok' => true], 200),
        'https://example.test/arcgis/FeatureServer/0/updateFeatures' => Http::response([
            'updateResults' => [['success' => true]],
        ], 200),
    ]);

    $manager = User::factory()->create(['name' => 'Manager User']);
    $manager->givePermissionTo([
        'view committee decisions',
        'manage committee decision content',
        'edit committee decisions',
    ]);

    $engineer = User::factory()->create([
        'name' => 'Engineer Field',
        'username_arcgis' => 'engineer.field.arcgis',
        'telegram_chat_id' => '99887766',
    ]);

    $signerOneUser = User::factory()->create();
    $signerTwoUser = User::factory()->create();
    $signerOneUser->givePermissionTo('sign committee decisions');
    $signerTwoUser->givePermissionTo('sign committee decisions');

    $memberOne = CommitteeMember::factory()->linkedUser($signerOneUser)->create([
        'name' => 'عضو أول',
        'sort_order' => 1,
        'is_required' => true,
    ]);
    $memberTwo = CommitteeMember::factory()->linkedUser($signerTwoUser)->create([
        'name' => 'عضو ثان',
        'sort_order' => 2,
        'is_required' => true,
    ]);

    $building = Building::query()->create([
        'objectid' => 9301,
        'globalid' => 'building-guid-2',
        'building_name' => 'عمارة الصفا',
        'neighborhood' => 'تل الهوا',
        'assignedto' => $engineer->username_arcgis,
        'building_damage_status' => 'committee_review',
    ]);

    $this->actingAs($manager)->put(route('committee-decisions.update', CommitteeDecision::query()->firstOrCreate([
        'decisionable_type' => Building::class,
        'decisionable_id' => $building->id,
    ], [
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ])), [
        'decision_type' => 'accepted',
        'decision_text' => 'تم اعتماد القرار النهائي للمبنى.',
        'action_text' => 'تنفيذ الإجراء الميداني',
        'notes' => 'تمت المراجعة',
        'decision_date' => '2026-04-21',
    ])->assertRedirect();

    $decision = CommitteeDecision::query()->whereMorphedTo('decisionable', $building)->firstOrFail();

    $this->actingAs($signerOneUser)->post(route('committee-decisions.sign', $decision), [
        'committee_member_id' => $memberOne->id,
        'status' => 'approved',
        'notes' => 'موافق',
    ])->assertRedirect();

    $this->actingAs($signerTwoUser)->post(route('committee-decisions.sign', $decision), [
        'committee_member_id' => $memberTwo->id,
        'status' => 'approved',
        'notes' => 'تم التوقيع',
    ])->assertRedirect();

    $decision->refresh();

    expect($decision->status)->toBe('completed');
    expect($decision->completed_at)->not->toBeNull();
    expect($decision->telegram_status)->toBe('sent');
    expect($decision->arcgis_sync_status)->toBe('synced');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.telegram.org/bottelegram-bot-token/sendMessage'
            && $request['chat_id'] === '99887766';
    });
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/updateFeatures'));
});

it('allows retrying telegram for a completed decision using the default group chat', function () {
    config()->set('services.committee_decisions.telegram.bot_token', 'telegram-bot-token');
    config()->set('services.committee_decisions.telegram.chat_id', '-1001234567890');

    Http::fake([
        'https://api.telegram.org/bottelegram-bot-token/sendMessage' => Http::response(['ok' => true], 200),
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo(['view committee decisions', 'send committee telegram']);

    $building = Building::query()->create([
        'objectid' => 9501,
        'globalid' => 'building-guid-retry',
        'building_name' => 'Retry Building',
        'neighborhood' => 'Rimal',
        'assignedto' => 'unmatched.engineer',
        'building_damage_status' => 'committee_review',
    ]);

    $decision = CommitteeDecision::query()->create([
        'decisionable_type' => Building::class,
        'decisionable_id' => $building->id,
        'decision_type' => 'accepted',
        'decision_text' => 'Ready to resend',
        'decision_date' => '2026-04-22',
        'status' => CommitteeDecision::STATUS_COMPLETED,
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'committee_manager_id' => $user->id,
        'completed_at' => now(),
        'telegram_status' => 'missing_chat_id',
    ]);

    $this->actingAs($user)
        ->post(route('committee-decisions.retry-telegram', $decision))
        ->assertRedirect();

    $this->artisan('queue:work --once')->assertExitCode(0);

    $decision->refresh();

    expect($decision->telegram_status)->toBe('sent');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.telegram.org/bottelegram-bot-token/sendMessage'
            && $request['chat_id'] === '-1001234567890';
    });
});
