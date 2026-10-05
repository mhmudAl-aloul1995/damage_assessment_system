<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $statusReferenceTables = [
        'building_statuses',
        'building_status_histories',
        'housing_statuses',
        'housing_status_histories',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('assessment_statuses') || ! Schema::hasColumn('assessment_statuses', 'name')) {
            return;
        }

        $duplicateGroups = DB::table('assessment_statuses')
            ->select('name')
            ->whereNotNull('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicateGroups as $name) {
            $ids = DB::table('assessment_statuses')
                ->where('name', $name)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();

            $canonicalId = $ids->first();
            $duplicateIds = $ids->skip(1)->values();

            if (! $canonicalId || $duplicateIds->isEmpty()) {
                continue;
            }

            foreach ($this->statusReferenceTables as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'status_id')) {
                    DB::table($table)
                        ->whereIn('status_id', $duplicateIds)
                        ->update(['status_id' => $canonicalId]);
                }
            }

            DB::table('assessment_statuses')
                ->whereIn('id', $duplicateIds)
                ->delete();
        }

        if (! $this->indexExists('assessment_statuses', 'assessment_statuses_name_unique')) {
            Schema::table('assessment_statuses', function (Blueprint $table) {
                $table->unique('name', 'assessment_statuses_name_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (
            Schema::hasTable('assessment_statuses')
            && $this->indexExists('assessment_statuses', 'assessment_statuses_name_unique')
        ) {
            Schema::table('assessment_statuses', function (Blueprint $table) {
                $table->dropUnique('assessment_statuses_name_unique');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");

            foreach ($rows as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);

        return count($rows) > 0;
    }
};
