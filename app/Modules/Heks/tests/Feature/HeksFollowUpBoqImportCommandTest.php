<?php

use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Services\HeksSpreadsheetImportService;

test('heks follow-up boq import command imports only follow-ups without existing boq items by default', function () {
    $beneficiary = HeksBeneficiary::query()->create([
        'code' => 'CMD1',
        'name' => 'Command Beneficiary',
    ]);

    $pendingFollowUp = HeksFollowUp::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'code' => $beneficiary->code,
        'visit_number' => '1',
        'boq_filename' => 'visit-one.xlsx',
        'boq_url' => 'https://example.test/visit-one.xlsx',
    ]);

    $alreadyImportedFollowUp = HeksFollowUp::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'code' => $beneficiary->code,
        'visit_number' => '2',
        'boq_filename' => 'visit-two.xlsx',
        'boq_url' => 'https://example.test/visit-two.xlsx',
    ]);

    HeksBoqItem::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'heks_follow_up_id' => $alreadyImportedFollowUp->id,
        'source' => 'visit-two.xlsx',
        'description' => 'Existing BOQ item',
        'quantity' => 1,
        'unit_price_ils' => 10,
        'total_price_ils' => 10,
    ]);

    $this->mock(HeksSpreadsheetImportService::class, function ($mock) use ($pendingFollowUp): void {
        $mock->shouldReceive('importFollowUpBoq')
            ->once()
            ->withArgs(fn (HeksFollowUp $followUp): bool => $followUp->is($pendingFollowUp))
            ->andReturn([
                'imported' => true,
                'imported_rows' => 3,
            ]);
    });

    $this->artisan('heks:import-followup-boqs', ['--code' => 'CMD1'])
        ->expectsOutputToContain('imported 3 rows')
        ->expectsOutputToContain('Processed: 1, imported: 1, skipped: 0, failed: 0')
        ->assertSuccessful();
});
