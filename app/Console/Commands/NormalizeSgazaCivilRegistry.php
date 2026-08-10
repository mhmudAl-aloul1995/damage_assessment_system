<?php

namespace App\Console\Commands;

use App\Support\ArabicNameNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NormalizeSgazaCivilRegistry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sgaza-civil-registry:normalize {--chunk=1000 : Rows processed per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build normalized lookup names for the sgaza civil registry table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Schema::hasTable('sgaza')) {
            $this->warn('Table sgaza was not found.');

            return self::SUCCESS;
        }

        if (! Schema::hasColumn('sgaza', 'full_name') || ! Schema::hasColumn('sgaza', 'full_name_normalized')) {
            $this->error('Run migrations first so sgaza has full_name and full_name_normalized columns.');

            return self::FAILURE;
        }

        $chunkSize = max(100, (int) $this->option('chunk'));
        $processed = 0;

        DB::table('sgaza')
            ->select(['id_number', 'first_name', 'father_name', 'grandfather_name', 'family_name'])
            ->whereNotNull('id_number')
            ->where('id_number', '<>', '')
            ->orderBy('id_number')
            ->chunk($chunkSize, function ($records) use (&$processed): void {
                foreach ($records as $record) {
                    $fullName = trim(implode(' ', array_filter([
                        trim((string) $record->first_name),
                        trim((string) $record->father_name),
                        trim((string) $record->grandfather_name),
                        trim((string) $record->family_name),
                    ])));

                    DB::table('sgaza')
                        ->where('id_number', $record->id_number)
                        ->update([
                            'full_name' => $fullName !== '' ? $fullName : null,
                            'full_name_normalized' => ArabicNameNormalizer::normalize($fullName),
                        ]);
                }

                $processed += $records->count();
                $this->line("Normalized {$processed} sgaza records...");
            });

        $this->info("SGaza civil registry normalized. Processed {$processed} records.");

        return self::SUCCESS;
    }
}
