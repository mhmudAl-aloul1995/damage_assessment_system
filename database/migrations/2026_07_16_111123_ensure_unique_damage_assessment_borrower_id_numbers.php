<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->mergeDuplicateBorrowers();

        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            $table->unique('borrower_id_number', 'damage_assessment_borrowers_borrower_id_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            $table->dropUnique('damage_assessment_borrowers_borrower_id_number_unique');
        });
    }

    private function mergeDuplicateBorrowers(): void
    {
        $duplicateIdNumbers = DB::table('damage_assessment_borrowers')
            ->select('borrower_id_number')
            ->whereNotNull('borrower_id_number')
            ->where('borrower_id_number', '!=', '')
            ->groupBy('borrower_id_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('borrower_id_number');

        foreach ($duplicateIdNumbers as $borrowerIdNumber) {
            $borrowerIds = DB::table('damage_assessment_borrowers')
                ->where('borrower_id_number', $borrowerIdNumber)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->pluck('id')
                ->all();

            $keeperId = array_shift($borrowerIds);

            if ($keeperId === null || $borrowerIds === []) {
                continue;
            }

            $this->mergeUniqueChildren('damage_assessment_borrower_boq_items', 'source_key', (int) $keeperId, $borrowerIds);
            $this->mergeUniqueChildren('damage_assessment_borrower_attachments', 'source_index', (int) $keeperId, $borrowerIds);
            $this->mergeUniqueChildren('damage_assessment_borrower_resident_households', 'source_index', (int) $keeperId, $borrowerIds);

            if (Schema::hasTable('kobo_rest_submissions') && Schema::hasColumn('kobo_rest_submissions', 'damage_assessment_borrower_id')) {
                DB::table('kobo_rest_submissions')
                    ->whereIn('damage_assessment_borrower_id', $borrowerIds)
                    ->update(['damage_assessment_borrower_id' => $keeperId]);
            }

            DB::table('damage_assessment_borrowers')
                ->whereIn('id', $borrowerIds)
                ->delete();
        }
    }

    /**
     * @param  array<int, int|string>  $duplicateBorrowerIds
     */
    private function mergeUniqueChildren(string $table, string $uniqueColumn, int $keeperId, array $duplicateBorrowerIds): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'damage_assessment_borrower_id')) {
            return;
        }

        $existingValues = DB::table($table)
            ->where('damage_assessment_borrower_id', $keeperId)
            ->whereNotNull($uniqueColumn)
            ->pluck($uniqueColumn)
            ->filter()
            ->all();

        if ($existingValues !== []) {
            DB::table($table)
                ->whereIn('damage_assessment_borrower_id', $duplicateBorrowerIds)
                ->whereIn($uniqueColumn, $existingValues)
                ->delete();
        }

        DB::table($table)
            ->whereIn('damage_assessment_borrower_id', $duplicateBorrowerIds)
            ->update(['damage_assessment_borrower_id' => $keeperId]);
    }
};
