<?php

namespace App\Console\Commands;

use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksWorkAssignment;
use App\Modules\Heks\Services\HeksEngineerUserResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class LinkHeksEngineersToUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heks:link-engineers-to-users
        {--dry-run : Preview matches without updating records}
        {--unmatched : Show engineer names that do not match any user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Link HEKS engineer text fields to users.id when names match existing users';

    /**
     * Execute the console command.
     */
    public function handle(HeksEngineerUserResolver $resolver): int
    {
        $linked = 0;
        $unmatched = collect();

        HeksBeneficiary::query()
            ->whereNotNull('field_engineer')
            ->orderBy('id')
            ->chunkById(100, function ($beneficiaries) use ($resolver, &$linked, $unmatched): void {
                foreach ($beneficiaries as $beneficiary) {
                    $linked += $this->linkRecord($beneficiary, 'field_engineer', 'field_engineer_user_id', $resolver, $unmatched);
                }
            });

        HeksFollowUp::query()
            ->whereNotNull('engineer_name')
            ->orderBy('id')
            ->chunkById(100, function ($followUps) use ($resolver, &$linked, $unmatched): void {
                foreach ($followUps as $followUp) {
                    $linked += $this->linkRecord($followUp, 'engineer_name', 'engineer_user_id', $resolver, $unmatched);
                }
            });

        HeksWorkAssignment::query()
            ->whereNotNull('engineer_name')
            ->orderBy('id')
            ->chunkById(100, function ($assignments) use ($resolver, &$linked, $unmatched): void {
                foreach ($assignments as $assignment) {
                    $linked += $this->linkRecord($assignment, 'engineer_name', 'engineer_user_id', $resolver, $unmatched);
                }
            });

        if ($this->option('unmatched')) {
            $this->showUnmatched($unmatched);
        }

        $this->components->info("HEKS engineer user linking finished. Matched records: {$linked}.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, string>  $unmatched
     */
    private function linkRecord(object $record, string $nameColumn, string $userColumn, HeksEngineerUserResolver $resolver, Collection $unmatched): int
    {
        $engineerName = trim((string) $record->{$nameColumn});
        $userId = $resolver->resolve($engineerName);

        if ($userId === null) {
            if ($engineerName !== '') {
                $unmatched->push($engineerName);
            }

            return 0;
        }

        if ((int) $record->{$userColumn} === $userId) {
            return 0;
        }

        $this->line(class_basename($record).' #'.$record->id.' '.$engineerName.' -> user '.$userId);

        if (! $this->option('dry-run')) {
            $record->forceFill([$userColumn => $userId])->save();
        }

        return 1;
    }

    /**
     * @param  Collection<int, string>  $unmatched
     */
    private function showUnmatched(Collection $unmatched): void
    {
        $names = $unmatched
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(30);

        if ($names->isEmpty()) {
            $this->line('No unmatched HEKS engineer names found.');

            return;
        }

        $this->warn('Unmatched HEKS engineer names:');
        $names->each(fn (int $count, string $name): mixed => $this->line("- {$name} ({$count})"));
    }
}
