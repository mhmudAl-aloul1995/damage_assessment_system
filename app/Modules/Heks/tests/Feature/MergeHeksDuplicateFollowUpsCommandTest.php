<?php

use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksBoqItem;
use App\Modules\Heks\Models\HeksFollowUp;

test('heks duplicate follow-up merge keeps visit data and attached boq rows', function () {
    $beneficiary = HeksBeneficiary::query()->create([
        'code' => 'GDM4',
        'name' => 'Duplicate Visit Beneficiary',
    ]);

    $realVisit = HeksFollowUp::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'code' => $beneficiary->code,
        'visit_number' => '2',
        'visit_date' => '2026-06-08',
        'engineer_name' => 'Engineer',
        'working_condition' => 'work_in_progress__and_due_for_next_payme',
        'completed_amount_ils' => 5395,
        'completion_percentage' => 37,
        'engineer_recommendations' => 'Keep this recommendation',
        'raw_data' => ['source' => 'real'],
    ]);

    $fileOnlyVisit = HeksFollowUp::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'code' => $beneficiary->code,
        'visit_number' => '2.0',
        'visit_date' => '2026-06-08',
        'boq_filename' => 'visit-two.xlsx',
        'boq_url' => 'https://example.test/visit-two.xlsx',
        'raw_data' => ['source' => 'file'],
    ]);

    $dateAsVisit = HeksFollowUp::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'code' => $beneficiary->code,
        'visit_number' => '2026-06-08',
        'visit_date' => '2026-06-08',
        'raw_data' => ['source' => 'date-as-visit'],
    ]);

    $boqItem = HeksBoqItem::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'heks_follow_up_id' => $fileOnlyVisit->id,
        'source' => 'visit-two.xlsx',
        'description' => 'Follow-up BOQ item',
        'quantity' => 1,
        'unit_price_ils' => 100,
        'total_price_ils' => 100,
    ]);

    HeksAttachment::query()->create([
        'heks_beneficiary_id' => $beneficiary->id,
        'source' => "follow-up:{$fileOnlyVisit->id}",
        'attachment_type' => 'follow_up_boq',
        'filename' => 'visit-two.xlsx',
        'url' => 'https://example.test/visit-two.xlsx',
    ]);

    $this->artisan('heks:merge-duplicate-followups', ['--code' => 'GDM4'])
        ->expectsOutputToContain('GDM4 2026-06-08 visit 2')
        ->assertSuccessful();

    expect(HeksFollowUp::query()->where('code', 'GDM4')->count())->toBe(1);

    $mergedVisit = HeksFollowUp::query()->where('code', 'GDM4')->sole();

    expect($mergedVisit->id)->toBe($realVisit->id)
        ->and($mergedVisit->visit_number)->toBe('2')
        ->and((float) $mergedVisit->completed_amount_ils)->toBe(5395.0)
        ->and((float) $mergedVisit->completion_percentage)->toBe(37.0)
        ->and($mergedVisit->engineer_recommendations)->toBe('Keep this recommendation')
        ->and($mergedVisit->boq_filename)->toBe('visit-two.xlsx')
        ->and($mergedVisit->boq_url)->toBe('https://example.test/visit-two.xlsx')
        ->and($mergedVisit->raw_data)->toHaveKey("merged_follow_up_{$dateAsVisit->id}");

    expect($boqItem->refresh()->heks_follow_up_id)->toBe($mergedVisit->id)
        ->and(HeksAttachment::query()->where('source', "follow-up:{$mergedVisit->id}")->exists())->toBeTrue();
});
