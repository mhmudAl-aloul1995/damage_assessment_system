<?php

use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksFollowUp;

uses(Tests\TestCase::class);

test('beneficiary responsible engineer falls back to a clear follow up engineer when stored engineer is a raw Kobo code', function () {
    $beneficiary = new HeksBeneficiary([
        'field_engineer' => 'adham ____4',
    ]);

    $beneficiary->setRelation('followUps', collect([
        new HeksFollowUp([
            'engineer_name' => 'Clean Engineer',
        ]),
    ]));

    expect($beneficiary->responsibleEngineerName())->toBe('Clean Engineer')
        ->and(HeksBeneficiary::isRawEngineerCode('adham ____4'))->toBeTrue()
        ->and(HeksBeneficiary::isRawEngineerCode('Clean Engineer'))->toBeFalse();
});
