<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
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
    $response->assertSee(__('ui.exports.import_objectids_excel'));
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
