<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_building_surveys', function (Blueprint $table): void {
            if (! Schema::hasColumn('public_building_surveys', 'globalid')) {
                $table->string('globalid')->nullable()->after('objectid');
            }

            if (! Schema::hasColumn('public_building_surveys', 'occupied_stakeholders')) {
                $table->string('occupied_stakeholders')->nullable()->after('is_uxo');
            }

            if (! Schema::hasColumn('public_building_surveys', 'is_displaced')) {
                $table->string('is_displaced')->nullable()->after('occupied_stakeholders');
            }

            if (! Schema::hasColumn('public_building_surveys', 'building_boundaries')) {
                $table->string('building_boundaries')->nullable()->after('and_ownership');
            }

            if (! Schema::hasColumn('public_building_surveys', 'creationdate')) {
                $table->dateTime('creationdate')->nullable()->after('raw_payload');
            }

            if (! Schema::hasColumn('public_building_surveys', 'creator')) {
                $table->string('creator')->nullable()->after('creationdate');
            }

            if (! Schema::hasColumn('public_building_surveys', 'editdate')) {
                $table->dateTime('editdate')->nullable()->after('creator');
            }

            if (! Schema::hasColumn('public_building_surveys', 'editor')) {
                $table->string('editor')->nullable()->after('editdate');
            }
        });

        Schema::table('road_facility_surveys', function (Blueprint $table): void {
            if (! Schema::hasColumn('road_facility_surveys', 'globalid')) {
                $table->string('globalid')->nullable()->after('objectid');
            }

            if (! Schema::hasColumn('road_facility_surveys', 'road_type_note')) {
                $table->text('road_type_note')->nullable()->after('lane_count');
            }

            if (! Schema::hasColumn('road_facility_surveys', 'asphalt')) {
                $table->string('asphalt')->nullable()->after('other_read_type');
            }

            if (! Schema::hasColumn('road_facility_surveys', 'basecoarse')) {
                $table->string('basecoarse')->nullable()->after('asphalt');
            }

            if (! Schema::hasColumn('road_facility_surveys', 'curbstone_m')) {
                $table->decimal('curbstone_m', 12, 2)->nullable()->after('street_interlock_m2');
            }

            if (! Schema::hasColumn('road_facility_surveys', 'lighting_poles')) {
                $table->string('lighting_poles')->nullable()->after('lighting_electrical_network');
            }

            if (! Schema::hasColumn('road_facility_surveys', 'other_note')) {
                $table->text('other_note')->nullable()->after('handrails_painting_mr');
            }

            if (! Schema::hasColumn('road_facility_surveys', 'creationdate')) {
                $table->dateTime('creationdate')->nullable()->after('raw_payload');
            }

            if (! Schema::hasColumn('road_facility_surveys', 'creator')) {
                $table->string('creator')->nullable()->after('creationdate');
            }

            if (! Schema::hasColumn('road_facility_surveys', 'editdate')) {
                $table->dateTime('editdate')->nullable()->after('creator');
            }

            if (! Schema::hasColumn('road_facility_surveys', 'editor')) {
                $table->string('editor')->nullable()->after('editdate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('public_building_surveys', function (Blueprint $table): void {
            $columns = [
                'globalid',
                'occupied_stakeholders',
                'is_displaced',
                'building_boundaries',
                'creationdate',
                'creator',
                'editdate',
                'editor',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('public_building_surveys', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('road_facility_surveys', function (Blueprint $table): void {
            $columns = [
                'globalid',
                'road_type_note',
                'asphalt',
                'basecoarse',
                'curbstone_m',
                'lighting_poles',
                'other_note',
                'creationdate',
                'creator',
                'editdate',
                'editor',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('road_facility_surveys', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
