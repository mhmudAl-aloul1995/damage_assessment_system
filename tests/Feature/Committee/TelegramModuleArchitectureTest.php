<?php

use App\Models\Building;
use App\Models\CommitteeDecision;
use App\Models\TelegramDestination;
use App\Models\TelegramDiscoveredChat;
use App\Models\TelegramSetting;
use App\Models\User;
use App\services\TelegramConnectionService;
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

it('links a user destination through telegram deep linking', function () {
    TelegramSetting::query()->create([
        'bot_token' => 'telegram-token',
        'bot_username' => 'phc_bot',
        'webhook_secret' => 'secret-token',
        'is_enabled' => true,
        'parse_mode' => 'HTML',
    ]);

    $admin = User::factory()->create();
    $admin->givePermissionTo(['view telegram integrations', 'manage telegram integrations']);

    $engineer = User::factory()->create([
        'name' => 'Engineer Omar',
        'username_arcgis' => 'omar.arcgis',
    ]);

    $destination = app(TelegramConnectionService::class)->createDestination([
        'name' => 'Engineer Omar Direct',
        'type' => TelegramDestination::TYPE_USER,
        'scope_type' => 'system',
        'related_model_type' => User::class,
        'related_model_id' => $engineer->id,
    ], $admin);

    $session = $destination->linkSessions()->firstOrFail();

    $this->post(route('telegram.webhook', ['secret' => 'secret-token']), [
        'update_id' => 1,
        'message' => [
            'message_id' => 10,
            'text' => '/start td_'.$session->token,
            'chat' => [
                'id' => 55112233,
                'type' => 'private',
            ],
            'from' => [
                'id' => 55112233,
                'is_bot' => false,
                'first_name' => 'Omar',
                'last_name' => 'Engineer',
                'username' => 'engineer_omar',
            ],
        ],
    ])->assertOk();

    $destination->refresh();
    $session->refresh();

    expect($destination->status)->toBe(TelegramDestination::STATUS_CONNECTED);
    expect($destination->chat_id)->toBe('55112233');
    expect($destination->telegram_username)->toBe('engineer_omar');
    expect($session->status)->toBe('connected');
});

it('generates a telegram bot link for a user from user management without duplicating destinations', function () {
    TelegramSetting::query()->create([
        'bot_token' => 'telegram-token',
        'bot_username' => 'phc_bot',
        'webhook_secret' => 'secret-token',
        'is_enabled' => true,
        'parse_mode' => 'HTML',
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('Database Officer');
    $admin->givePermissionTo(['view telegram integrations', 'manage telegram integrations']);

    $engineer = User::factory()->create([
        'name' => 'Linked Engineer',
        'username_arcgis' => 'linked.engineer',
    ]);

    $firstResponse = $this->actingAs($admin)
        ->post(route('users.telegram-link', $engineer))
        ->assertOk()
        ->json();

    $secondResponse = $this->actingAs($admin)
        ->post(route('users.telegram-link', $engineer))
        ->assertOk()
        ->json();

    $destinations = TelegramDestination::query()
        ->where('type', TelegramDestination::TYPE_USER)
        ->where('related_model_type', User::class)
        ->where('related_model_id', $engineer->id)
        ->get();

    expect($destinations)->toHaveCount(1);
    expect($firstResponse['destination_id'])->toBe($secondResponse['destination_id']);
    expect($firstResponse['shareable_link'])->toStartWith('https://t.me/phc_bot?start=td_');
    expect($secondResponse['shareable_link'])->toStartWith('https://t.me/phc_bot?start=td_');
});

it('discovers telegram groups automatically and promotes them into destinations', function () {
    TelegramSetting::query()->create([
        'bot_token' => 'telegram-token',
        'bot_username' => 'phc_bot',
        'webhook_secret' => 'secret-token',
        'is_enabled' => true,
        'parse_mode' => 'HTML',
    ]);

    $admin = User::factory()->create();
    $admin->givePermissionTo(['view telegram integrations', 'manage telegram integrations']);

    $this->post(route('telegram.webhook', ['secret' => 'secret-token']), [
        'update_id' => 3,
        'message' => [
            'message_id' => 11,
            'text' => 'hello group',
            'chat' => [
                'id' => -100778899,
                'type' => 'supergroup',
                'title' => 'Operations Hub',
                'username' => 'ops_hub',
            ],
            'from' => [
                'id' => 12345,
                'is_bot' => false,
                'first_name' => 'Admin',
            ],
        ],
    ])->assertOk();

    $discoveredChat = TelegramDiscoveredChat::query()->firstOrFail();

    $this->actingAs($admin)->post(route('telegram.discovered.promote', $discoveredChat), [
        'name' => 'Operations Group',
        'scope_type' => 'system',
        'context_id' => null,
    ])->assertRedirect(route('telegram.discovered.index'));

    $destination = TelegramDestination::query()->where('name', 'Operations Group')->firstOrFail();
    $discoveredChat->refresh();

    expect($destination->type)->toBe(TelegramDestination::TYPE_GROUP);
    expect($destination->chat_id)->toBe('-100778899');
    expect($destination->status)->toBe(TelegramDestination::STATUS_CONNECTED);
    expect($discoveredChat->telegram_destination_id)->toBe($destination->id);
});

it('sends committee telegram notifications through linked destinations instead of users chat ids', function () {
    TelegramSetting::query()->create([
        'bot_token' => 'telegram-token',
        'bot_username' => 'phc_bot',
        'webhook_secret' => 'secret-token',
        'is_enabled' => true,
        'parse_mode' => 'HTML',
    ]);

    Http::fake([
        'https://api.telegram.org/bottelegram-token/sendMessage' => Http::response(['ok' => true], 200),
    ]);

    $actor = User::factory()->create();
    $actor->givePermissionTo(['view committee decisions', 'send committee telegram']);

    $engineer = User::factory()->create([
        'name' => 'Engineer Destination',
        'username_arcgis' => 'engineer.destination',
    ]);

    $destination = TelegramDestination::factory()->create([
        'name' => 'Engineer Direct Chat',
        'status' => TelegramDestination::STATUS_CONNECTED,
        'chat_id' => '99887766',
        'related_model_type' => User::class,
        'related_model_id' => $engineer->id,
        'telegram_username' => 'eng_dest',
    ]);

    $destination->preferences()->create([
        'notify_status_changes' => true,
    ]);

    $building = Building::query()->create([
        'objectid' => 9991,
        'globalid' => 'telegram-building',
        'building_name' => 'Destination Building',
        'neighborhood' => 'Rimal',
        'assignedto' => $engineer->username_arcgis,
        'building_damage_status' => 'committee_review',
    ]);

    $decision = CommitteeDecision::query()->create([
        'decisionable_type' => Building::class,
        'decisionable_id' => $building->id,
        'decision_type' => 'accepted',
        'decision_text' => 'Committee text',
        'decision_date' => now()->toDateString(),
        'status' => CommitteeDecision::STATUS_COMPLETED,
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
        'committee_manager_id' => $actor->id,
        'completed_at' => now(),
        'telegram_status' => 'failed',
    ]);

    $this->actingAs($actor)
        ->post(route('committee-decisions.retry-telegram', $decision))
        ->assertRedirect();

    $this->artisan('queue:work --once')->assertExitCode(0);

    $decision->refresh();

    expect($decision->telegram_status)->toBe('sent');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.telegram.org/bottelegram-token/sendMessage'
            && $request['chat_id'] === '99887766';
    });
});
