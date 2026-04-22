<?php

use App\Models\TelegramIntegration;
use App\Models\TelegramLinkSession;
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

    config()->set('services.telegram.bot_username', 'phc_bot');
    config()->set('services.telegram.bot_token', 'telegram-token');
    config()->set('services.telegram.webhook_secret', 'secret-token');
});

it('creates telegram user and group integrations with shareable links', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(['view telegram integrations', 'manage telegram integrations']);

    $engineer = User::factory()->create();

    $this->actingAs($admin)->post(route('telegram-integrations.store'), [
        'name' => 'Engineer Direct',
        'type' => TelegramIntegration::TYPE_USER,
        'user_id' => $engineer->id,
    ])->assertRedirect(route('telegram-integrations.index'));

    $this->actingAs($admin)->post(route('telegram-integrations.store'), [
        'name' => 'Field Group',
        'type' => TelegramIntegration::TYPE_GROUP,
    ])->assertRedirect(route('telegram-integrations.index'));

    $userIntegration = TelegramIntegration::query()->where('name', 'Engineer Direct')->firstOrFail();
    $groupIntegration = TelegramIntegration::query()->where('name', 'Field Group')->firstOrFail();

    $service = app(TelegramConnectionService::class);

    expect($service->shareableLink($userIntegration))->toStartWith('https://t.me/phc_bot?start=ti_');
    expect($service->shareableLink($groupIntegration))->toContain('startgroup=ti_');

    $this->actingAs($admin)
        ->get(route('telegram-integrations.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()
        ->assertSee('Engineer Direct')
        ->assertSee('Field Group');
});

it('connects a user integration through the telegram webhook and syncs the app user chat id', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(['view telegram integrations', 'manage telegram integrations']);

    $engineer = User::factory()->create();

    $integration = TelegramIntegration::factory()->create([
        'user_id' => $engineer->id,
        'created_by' => $admin->id,
        'name' => 'Engineer Telegram',
        'type' => TelegramIntegration::TYPE_USER,
        'status' => TelegramIntegration::STATUS_PENDING,
    ]);

    $session = TelegramLinkSession::factory()->create([
        'telegram_integration_id' => $integration->id,
        'status' => TelegramLinkSession::STATUS_PENDING,
    ]);

    $this->post(route('telegram.webhook', ['secret' => 'secret-token']), [
        'update_id' => 1001,
        'message' => [
            'message_id' => 15,
            'text' => '/start ti_'.$session->token,
            'chat' => [
                'id' => '55112233',
                'type' => 'private',
                'username' => 'engineer_omar',
                'first_name' => 'Omar',
                'last_name' => 'Field',
            ],
            'from' => [
                'id' => 77,
                'username' => 'engineer_omar',
                'first_name' => 'Omar',
                'last_name' => 'Field',
            ],
        ],
    ])->assertOk()->assertJson(['status' => 'connected']);

    $integration->refresh();
    $engineer->refresh();
    $session->refresh();

    expect($integration->status)->toBe(TelegramIntegration::STATUS_CONNECTED);
    expect($integration->telegram_chat_id)->toBe('55112233');
    expect($integration->telegram_username)->toBe('engineer_omar');
    expect($engineer->telegram_chat_id)->toBe('55112233');
    expect($session->status)->toBe(TelegramLinkSession::STATUS_CONNECTED);
});

it('prevents linking the same telegram group to more than one integration', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(['view telegram integrations', 'manage telegram integrations']);

    TelegramIntegration::factory()->create([
        'created_by' => $admin->id,
        'name' => 'Existing Group',
        'type' => TelegramIntegration::TYPE_GROUP,
        'status' => TelegramIntegration::STATUS_CONNECTED,
        'telegram_chat_id' => '-100778899',
        'telegram_title' => 'Existing Ops',
    ]);

    $integration = TelegramIntegration::factory()->create([
        'created_by' => $admin->id,
        'name' => 'New Group',
        'type' => TelegramIntegration::TYPE_GROUP,
        'status' => TelegramIntegration::STATUS_PENDING,
    ]);

    $session = TelegramLinkSession::factory()->create([
        'telegram_integration_id' => $integration->id,
        'status' => TelegramLinkSession::STATUS_PENDING,
    ]);

    $this->post(route('telegram.webhook', ['secret' => 'secret-token']), [
        'update_id' => 1002,
        'message' => [
            'message_id' => 16,
            'text' => '/start ti_'.$session->token,
            'chat' => [
                'id' => '-100778899',
                'type' => 'supergroup',
                'title' => 'Existing Ops',
            ],
            'from' => [
                'id' => 78,
                'username' => 'group_admin',
                'first_name' => 'Admin',
            ],
        ],
    ])->assertOk()->assertJson(['status' => 'failed']);

    $integration->refresh();
    $session->refresh();

    expect($integration->status)->toBe(TelegramIntegration::STATUS_FAILED);
    expect($integration->last_error)->toContain('already linked');
    expect($session->status)->toBe(TelegramLinkSession::STATUS_FAILED);
});

it('refreshes a connected integration status from telegram', function () {
    Http::fake([
        'https://api.telegram.org/bottelegram-token/getChat' => Http::response([
            'ok' => true,
            'result' => [
                'id' => '-100999888',
                'title' => 'Operations Hub',
                'type' => 'supergroup',
                'username' => 'ops_hub',
            ],
        ], 200),
    ]);

    $integration = TelegramIntegration::factory()->create([
        'name' => 'Ops Group',
        'type' => TelegramIntegration::TYPE_GROUP,
        'status' => TelegramIntegration::STATUS_CONNECTED,
        'telegram_chat_id' => '-100999888',
    ]);

    $result = app(TelegramConnectionService::class)->refreshStatus($integration);

    $integration->refresh();

    expect($result['success'])->toBeTrue();
    expect($integration->telegram_title)->toBe('Operations Hub');
    expect($integration->telegram_username)->toBe('ops_hub');
});
