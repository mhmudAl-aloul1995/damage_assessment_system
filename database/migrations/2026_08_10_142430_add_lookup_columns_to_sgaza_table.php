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
        if (! Schema::hasTable('sgaza')) {
            return;
        }

        Schema::table('sgaza', function (Blueprint $table) {
            if (! Schema::hasColumn('sgaza', 'full_name')) {
                $table->string('full_name')->nullable()->after('family_name');
            }

            if (! Schema::hasColumn('sgaza', 'full_name_normalized')) {
                $table->string('full_name_normalized')->nullable()->after('full_name');
            }
        });

        $this->addIndexIfMissing('sgaza', 'sgaza_id_number_index', ['id_number']);
        $this->addIndexIfMissing('sgaza', 'sgaza_full_name_normalized_index', ['full_name_normalized']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sgaza')) {
            return;
        }

        Schema::table('sgaza', function (Blueprint $table) {
            $this->dropIndexIfExists($table, 'sgaza_id_number_index');
            $this->dropIndexIfExists($table, 'sgaza_full_name_normalized_index');

            if (Schema::hasColumn('sgaza', 'full_name_normalized')) {
                $table->dropColumn('full_name_normalized');
            }

            if (Schema::hasColumn('sgaza', 'full_name')) {
                $table->dropColumn('full_name');
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();

        if (! $exists) {
            Schema::table($table, fn (Blueprint $table): mixed => $table->index($columns, $index));
        }
    }

    private function dropIndexIfExists(Blueprint $table, string $index): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table->getTable())
            ->where('index_name', $index)
            ->exists();

        if ($exists) {
            $table->dropIndex($index);
        }
    }
};
