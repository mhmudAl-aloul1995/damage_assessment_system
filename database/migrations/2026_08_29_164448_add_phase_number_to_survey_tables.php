<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'buildings',
        'housing_units',
        'public_building_surveys',
        'public_building_survey_units',
        'road_facility_surveys',
        'road_facility_survey_items',
        'cso_surveys',
        'cso_survey_organizations',
        'cso_survey_units',
        'audited_buildings',
        'audited_housing_units',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'phase_number')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedSmallInteger('phase_number')->default(1)->index();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'phase_number')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('phase_number');
            });
        }
    }
};
