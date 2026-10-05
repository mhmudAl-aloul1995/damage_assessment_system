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
        if (DB::connection()->getDriverName() === 'mysql' && $this->indexExists('missing_citizen_identity_reports', 'missing_citizen_identity_reports_unit_id_unique')) {
            DB::statement('ALTER TABLE missing_citizen_identity_reports DROP INDEX missing_citizen_identity_reports_unit_id_unique');
        }

        Schema::table('missing_citizen_identity_reports', function (Blueprint $table): void {
            $table->string('identity_subject', 20)->default('owner')->after('housing_unit_id');
            $table->unsignedTinyInteger('identity_index')->nullable()->after('identity_subject');
            $table->string('identity_name_field', 50)->default('unit_owner')->after('identity_index');
            $table->string('identity_number_field', 50)->default('id_number1')->after('identity_name_field');

            $table->unique(['housing_unit_id', 'identity_number_field'], 'missing_citizen_identity_reports_unit_field_unique');
            $table->index('identity_subject', 'missing_citizen_identity_reports_subject_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missing_citizen_identity_reports', function (Blueprint $table): void {
            $table->dropUnique('missing_citizen_identity_reports_unit_field_unique');
            $table->dropIndex('missing_citizen_identity_reports_subject_index');
            $table->dropColumn([
                'identity_subject',
                'identity_index',
                'identity_name_field',
                'identity_number_field',
            ]);
        });

        if (DB::connection()->getDriverName() === 'mysql' && ! $this->indexExists('missing_citizen_identity_reports', 'missing_citizen_identity_reports_unit_id_unique')) {
            DB::statement('ALTER TABLE missing_citizen_identity_reports ADD UNIQUE missing_citizen_identity_reports_unit_id_unique (housing_unit_id)');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = (string) config('database.connections.mysql.database');

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
