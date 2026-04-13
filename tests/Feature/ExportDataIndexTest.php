<?php

use App\Models\User;

it('shows the custom building units count field on the export page', function () {
    $user = User::factory()->make();

    $response = $this
        ->actingAs($user)
        ->get(route('export.data.index'));

    $response->assertOk();
    $response->assertSee('عدد الوحدات للمبنى');
    $response->assertSee('value="housing_units_count"', false);
});
