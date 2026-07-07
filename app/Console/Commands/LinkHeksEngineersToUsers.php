<?php

namespace App\Console\Commands;

use App\Modules\Heks\Models\HeksBeneficiary;
use App\Modules\Heks\Models\HeksFollowUp;
use App\Modules\Heks\Models\HeksWorkAssignment;
use App\Modules\Heks\Services\HeksEngineerUserResolver;
use Illuminate\Console\Command;

class LinkHeksEngineersToUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'heks:link-engineers-to-users
        {--dry-run : Preview matches without updating records}';

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

        HeksBeneficiary::query()
            ->whereNotNull('field_engineer')
            ->orderBy('id')
            ->chunkById(100, function ($beneficiaries) use ($resolver, &$linked): void {
                foreach ($beneficiaries as $beneficiary) {
                    $linked += $this->linkRecord($beneficiary, 'field_engineer', 'field_engineer_user_id', $resolver);
                }
            });

        HeksFollowUp::query()
            ->whereNotNull('engineer_name')
            ->orderBy('id')
            ->chunkById(100, function ($followUps) use ($resolver, &$linked): void {
                foreach ($followUps as $followUp) {
                    $linked += $this->linkRecord($followUp, 'engineer_name', 'engineer_user_id', $resolver);
                }
            });

        HeksWorkAssignment::query()
            ->whereNotNull('engineer_name')
            ->orderBy('id')
            ->chunkById(100, function ($assignments) use ($resolver, &$linked): void {
                foreach ($assignments as $assignment) {
                    $linked += $this->linkRecord($assignment, 'engineer_name', 'engineer_user_id', $resolver);
                }
            });

        $this->components->info("HEKS engineer user linking finished. Matched records: {$linked}.");

        return self::SUCCESS;
    }

    private function linkRecord(object $record, string $nameColumn, string $userColumn, HeksEngineerUserResolver $resolver): int
    {
        $userId = $resolver->resolve($record->{$nameColumn});

        if ($userId === null || (int) $record->{$userColumn} === $userId) {
            return 0;
        }

        $this->line(class_basename($record).' #'.$record->id.' '.$record->{$nameColumn}.' -> user '.$userId);

        if (! $this->option('dry-run')) {
            $record->forceFill([$userColumn => $userId])->save();
        }

        return 1;
    }
}
