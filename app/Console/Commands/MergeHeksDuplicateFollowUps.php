<?php

namespace App\Console\Commands;

use App\Modules\Heks\Models\HeksAttachment;
use App\Modules\Heks\Models\HeksFollowUp;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class MergeHeksDuplicateFollowUps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heks:merge-duplicate-followups
        {--code= : Merge duplicate follow-ups for one beneficiary code}
        {--dry-run : Show duplicates without changing data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge duplicate HEKS follow-up rows created by different visit number formats';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $merged = 0;
        $groups = 0;

        $query = HeksFollowUp::query()
            ->with('boqItems')
            ->orderBy('heks_beneficiary_id')
            ->orderBy('code')
            ->orderBy('visit_date')
            ->orderBy('id');

        if (filled($this->option('code'))) {
            $query->where('code', (string) $this->option('code'));
        }

        $query->get()
            ->groupBy(fn (HeksFollowUp $followUp): string => $this->duplicateKey($followUp))
            ->filter(fn (Collection $followUps): bool => $followUps->count() > 1)
            ->each(function (Collection $followUps) use (&$merged, &$groups): void {
                $groups++;
                $primary = $this->primaryFollowUp($followUps);

                $this->line("{$primary->code} {$primary->visit_date?->toDateString()} visit {$primary->visit_number}: {$followUps->count()} rows");

                if ($this->option('dry-run')) {
                    return;
                }

                $followUps
                    ->reject(fn (HeksFollowUp $followUp): bool => $followUp->is($primary))
                    ->each(function (HeksFollowUp $duplicate) use ($primary, &$merged): void {
                        $this->mergeFollowUp($primary, $duplicate);
                        $merged++;
                    });
            });

        $this->components->info("HEKS duplicate follow-ups merge finished. Groups: {$groups}, merged rows: {$merged}.");

        return self::SUCCESS;
    }

    private function duplicateKey(HeksFollowUp $followUp): string
    {
        $visitDate = $followUp->visit_date?->toDateString();
        $visitNumber = HeksFollowUp::normalizeVisitNumber($followUp->visit_number);

        if ($visitDate !== null) {
            return implode('|', [
                (string) $followUp->heks_beneficiary_id,
                (string) $followUp->code,
                $visitDate,
            ]);
        }

        return implode('|', [
            (string) $followUp->heks_beneficiary_id,
            (string) $followUp->code,
            $visitNumber ?? '',
        ]);
    }

    /**
     * @param  Collection<int, HeksFollowUp>  $followUps
     */
    private function primaryFollowUp(Collection $followUps): HeksFollowUp
    {
        return $followUps
            ->sortByDesc(fn (HeksFollowUp $followUp): int => $this->dataScore($followUp))
            ->first();
    }

    private function dataScore(HeksFollowUp $followUp): int
    {
        return collect([
            $followUp->engineer_name,
            $followUp->working_condition,
            $followUp->other_condition,
            $followUp->completed_amount_ils,
            $followUp->completion_percentage,
            $followUp->engineer_recommendations,
            $followUp->boq_filename,
            $followUp->boq_url,
        ])->filter(fn (mixed $value): bool => filled($value))->count()
            + ($followUp->boqItems->count() * 2);
    }

    private function mergeFollowUp(HeksFollowUp $primary, HeksFollowUp $duplicate): void
    {
        $primaryVisitNumber = HeksFollowUp::normalizeVisitNumber($primary->visit_number);
        $duplicateVisitNumber = HeksFollowUp::normalizeVisitNumber($duplicate->visit_number);

        if ($primaryVisitNumber !== $primary->visit_number) {
            $primary->visit_number = $primaryVisitNumber;
        }

        if (! filled($primary->visit_number) && filled($duplicateVisitNumber)) {
            $primary->visit_number = $duplicateVisitNumber;
        }

        foreach ($this->mergeableColumns() as $column) {
            if (! filled($primary->{$column}) && filled($duplicate->{$column})) {
                $primary->{$column} = $duplicate->{$column};
            }
        }

        $primary->raw_data = array_merge($primary->raw_data ?? [], [
            "merged_follow_up_{$duplicate->id}" => $duplicate->raw_data,
        ]);
        $primary->save();

        $duplicate->boqItems()->update([
            'heks_follow_up_id' => $primary->id,
        ]);

        HeksAttachment::query()
            ->where('source', "follow-up:{$duplicate->id}")
            ->update([
                'source' => "follow-up:{$primary->id}",
            ]);

        $duplicate->delete();
    }

    /**
     * @return array<int, string>
     */
    private function mergeableColumns(): array
    {
        return [
            'visit_date',
            'engineer_name',
            'working_condition',
            'other_condition',
            'completed_amount_ils',
            'completion_percentage',
            'engineer_recommendations',
            'boq_filename',
            'boq_url',
        ];
    }
}
