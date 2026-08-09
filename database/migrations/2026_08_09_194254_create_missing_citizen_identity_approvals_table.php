<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('missing_citizen_identity_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('missing_citizen_identity_report_id')->nullable();
            $table->unsignedBigInteger('housing_unit_id');
            $table->unsignedBigInteger('housing_unit_objectid')->nullable();
            $table->string('old_id_number')->nullable();
            $table->string('new_id_number');
            $table->string('owner_name')->nullable();
            $table->unsignedBigInteger('citizen_id');
            $table->string('citizen_full_name')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('arcgis_sync_status', 30)->nullable();
            $table->text('arcgis_sync_message')->nullable();
            $table->json('arcgis_sync_response')->nullable();
            $table->timestamps();

            $table->index('housing_unit_id', 'missing_citizen_identity_approvals_unit_index');
            $table->index('citizen_id', 'missing_citizen_identity_approvals_citizen_index');
            $table->index('approved_by', 'missing_citizen_identity_approvals_user_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missing_citizen_identity_approvals');
    }
};
