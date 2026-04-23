<?php

use App\Models\User;

it('shows export page actions including objectid import', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response->assertOk();
    $response->assertSee('value="housing_units_count"', false);
    $response->assertSee(__('ui.exports.import_objectids_excel'));
});
