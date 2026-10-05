<?php

use App\Models\User;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksWorkAssignment;

test('heks engineer linking command links existing text names to users', function () {
    $engineer = User::factory()->create([
        'name' => 'م مصطفى رضوان',
    ]);

    $beneficiary = HeksBeneficiary::query()->create([
        'code' => 'LINK1',
        'name' => 'Linked Beneficiary',
        'field_engineer' => 'م. مصطفى رضوان',
    ]);

    $followUp = HeksFollowUp::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'code' => $beneficiary->code,
        'visit_number' => '1',
        'engineer_name' => 'مصطفى رضوان',
    ]);

    $assignment = HeksWorkAssignment::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'source' => 'test',
        'engineer_name' => 'م مصطفى رضوان',
    ]);

    $this->artisan('heks:link-engineers-to-users')
        ->expectsOutputToContain('Matched records: 3')
        ->assertSuccessful();

    expect($beneficiary->refresh()->field_engineer_user_id)->toBe($engineer->id)
        ->and($followUp->refresh()->engineer_user_id)->toBe($engineer->id)
        ->and($assignment->refresh()->engineer_user_id)->toBe($engineer->id);
});
