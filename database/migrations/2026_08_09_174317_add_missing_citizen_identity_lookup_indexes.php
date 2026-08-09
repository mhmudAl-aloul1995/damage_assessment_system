<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! $this->indexExists((string) config('database.connections.mysql.database'), 'housing_units', 'housing_units_id_number1_index')) {
            DB::statement('ALTER TABLE housing_units ADD INDEX housing_units_id_number1_index (id_number1(11))');
        }

        if (! $this->indexExists('phc_dashboard', 'citizens', 'citizens_id_card_no_status_index')) {
            DB::statement('ALTER TABLE phc_dashboard.citizens ADD INDEX citizens_id_card_no_status_index (id_card_no, status)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if ($this->indexExists((string) config('database.connections.mysql.database'), 'housing_units', 'housing_units_id_number1_index')) {
            DB::statement('ALTER TABLE housing_units DROP INDEX housing_units_id_number1_index');
        }

        if ($this->indexExists('phc_dashboard', 'citizens', 'citizens_id_card_no_status_index')) {
            DB::statement('ALTER TABLE phc_dashboard.citizens DROP INDEX citizens_id_card_no_status_index');
        }
    }

    private function indexExists(string $schema, string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', $schema)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
