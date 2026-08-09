<?php

namespace App\Console\Commands;

use App\Models\HousingUnit;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshMissingCitizenIdentityReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'missing-citizen-identities:refresh {--chunk=5000 : Housing units processed per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the cached report of housing unit identity numbers missing from active citizens';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = max(100, (int) $this->option('chunk'));
        $stagingTable = 'missing_citizen_identity_reports_staging';

        $this->prepareStagingTable($stagingTable);

        $processed = 0;
        $missing = 0;

        HousingUnit::query()
            ->select(['id', 'unit_owner', 'id_number1'])
            ->whereNotNull('id_number1')
            ->where('id_number1', '<>', '')
            ->orderBy('id')
            ->chunkById($chunkSize, function ($housingUnits) use ($stagingTable, &$processed, &$missing): void {
                $processed += $housingUnits->count();

                $identityNumbers = $housingUnits
                    ->pluck('id_number1')
                    ->filter()
                    ->map(fn ($value): string => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->values();

                if ($identityNumbers->isEmpty()) {
                    return;
                }

                $activeCitizenIds = DB::table($this->citizensTable())
                    ->where('status', 'A')
                    ->whereIn('id_card_no', $identityNumbers)
                    ->pluck('id_card_no')
                    ->mapWithKeys(fn ($id): array => [(string) $id => true]);

                $rows = $housingUnits
                    ->filter(fn (HousingUnit $housingUnit): bool => ! isset($activeCitizenIds[trim((string) $housingUnit->id_number1)]))
                    ->map(fn (HousingUnit $housingUnit): array => [
                        'housing_unit_id' => $housingUnit->id,
                        'owner_name' => $housingUnit->unit_owner,
                        'id_number' => trim((string) $housingUnit->id_number1),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                    ->values();

                if ($rows->isEmpty()) {
                    return;
                }

                DB::table($stagingTable)->insert($rows->all());
                $missing += $rows->count();

                $this->line("Processed {$processed} housing units; cached {$missing} missing IDs.");
            });

        DB::transaction(function () use ($stagingTable): void {
            DB::table('missing_citizen_identity_reports')->delete();

            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("
                    INSERT INTO missing_citizen_identity_reports
                        (housing_unit_id, owner_name, id_number, created_at, updated_at)
                    SELECT housing_unit_id, owner_name, id_number, created_at, updated_at
                    FROM {$stagingTable}
                ");
            } else {
                DB::table('missing_citizen_identity_reports')->insert(
                    DB::table($stagingTable)
                        ->select(['housing_unit_id', 'owner_name', 'id_number', 'created_at', 'updated_at'])
                        ->get()
                        ->map(fn ($row): array => (array) $row)
                        ->all()
                );
            }
        });

        Schema::drop($stagingTable);

        $this->info("Report refreshed. Processed {$processed} housing units and cached {$missing} missing IDs.");

        return self::SUCCESS;
    }

    private function prepareStagingTable(string $stagingTable): void
    {
        Schema::dropIfExists($stagingTable);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("CREATE TABLE {$stagingTable} LIKE missing_citizen_identity_reports");

            return;
        }

        Schema::create($stagingTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('housing_unit_id');
            $table->string('owner_name')->nullable();
            $table->string('id_number', 255);
            $table->timestamps();
        });
    }

    private function citizensTable(): string
    {
        if (app()->environment('testing')) {
            return 'citizens';
        }

        return 'phc_dashboard.citizens';
    }
}
