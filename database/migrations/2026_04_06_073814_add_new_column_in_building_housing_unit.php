<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $assessmentNames = DB::table('assessments')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        $buildingColumns = Schema::getColumnListing('buildings');
        $housingColumns  = Schema::getColumnListing('housing_units');

        $missingInBuildings = [];
        $missingInHousing   = [];

        foreach ($assessmentNames as $name) {
            if ($this->isSection($name)) {
                continue;
            }

            // already exists in one of the tables
            if (in_array($name, $buildingColumns, true) || in_array($name, $housingColumns, true)) {
                continue;
            }

            if ($this->belongsToBuildings($name)) {
                $missingInBuildings[] = $name;
                continue;
            }

            if ($this->belongsToHousing($name)) {
                $missingInHousing[] = $name;
                continue;
            }

            // unknown fields:
            // choose one:
            // 1) skip them safely
            // 2) log them
            // 3) default them to housing/buildings
        }

        if (!empty($missingInBuildings)) {
            Schema::table('buildings', function (Blueprint $table) use ($missingInBuildings) {
                foreach ($missingInBuildings as $column) {
                    $table->text($column)->nullable();
                }
            });
        }

        if (!empty($missingInHousing)) {
            Schema::table('housing_units', function (Blueprint $table) use ($missingInHousing) {
                foreach ($missingInHousing as $column) {
                    $table->text($column)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $buildingColumnsToDrop = [
            // put only columns actually added by this migration
        ];

        $housingColumnsToDrop = [
            // put only columns actually added by this migration
        ];

        if (!empty($buildingColumnsToDrop)) {
            Schema::table('buildings', function (Blueprint $table) use ($buildingColumnsToDrop) {
                $existing = array_filter($buildingColumnsToDrop, fn ($col) => Schema::hasColumn('buildings', $col));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }

        if (!empty($housingColumnsToDrop)) {
            Schema::table('housing_units', function (Blueprint $table) use ($housingColumnsToDrop) {
                $existing = array_filter($housingColumnsToDrop, fn ($col) => Schema::hasColumn('housing_units', $col));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }
    }

    private function isSection(string $name): bool
    {
        return in_array($name, [
            'attachments',
            'g0', 'g1',
            'building_information',
            'bldng_introduction',
            'ownweship_information',
            'ownweshipbldng_introduction',
            'documents',
            'disputes',
            'building_attachment',
            'building_services',
            'bldng_accessories',
            'bldng_engineer_comments',
            'housing_unit',
            'housing_unit_group',
            'page8', 'page9', 'page10', 'page11', 'page12', 'page13', 'page14',
            'mhpss',
            'ce',
        ], true);
    }

    private function belongsToBuildings(string $name): bool
    {
        return in_array($name, [
            'id_number_photo',
            'land_ownership_photo',
            'municipal_permit_photo',
            'other_documents_photo',
            'building_image',
            'building_image2',
        ], true);
    }

    private function belongsToHousing(string $name): bool
    {
        return in_array($name, [
            'ownership_image',
        ], true);
    }
};