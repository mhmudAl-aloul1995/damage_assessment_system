<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->index('assignedto', 'buildings_assignedto_index');
            $table->index('globalid', 'buildings_globalid_index');
            $table->index('creationdate', 'buildings_creationdate_index');
            $table->index('municipalitie', 'buildings_municipalitie_index');
            $table->index('neighborhood', 'buildings_neighborhood_index');
        });
//sss
        Schema::table('housing_units', function (Blueprint $table) {
            $table->index('parentglobalid', 'housing_units_parentglobalid_index');
            $table->index('globalid', 'housing_units_globalid_index');
        });

        Schema::table('edit_assessments', function (Blueprint $table) {
            $table->index('global_id', 'edit_assessments_global_id_index');
            $table->index('type', 'edit_assessments_type_index');
            $table->index('updated_at', 'edit_assessments_updated_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropIndex('buildings_assignedto_index');
            $table->dropIndex('buildings_globalid_index');
            $table->dropIndex('buildings_creationdate_index');
            $table->dropIndex('buildings_municipalitie_index');
            $table->dropIndex('buildings_neighborhood_index');
        });

        Schema::table('housing_units', function (Blueprint $table) {
            $table->dropIndex('housing_units_parentglobalid_index');
            $table->dropIndex('housing_units_globalid_index');
        });

        Schema::table('edit_assessments', function (Blueprint $table) {
            $table->dropIndex('edit_assessments_global_id_index');
            $table->dropIndex('edit_assessments_type_index');
            $table->dropIndex('edit_assessments_updated_at_index');
        });
    }
};
