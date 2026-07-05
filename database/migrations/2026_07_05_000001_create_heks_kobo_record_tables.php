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
        Schema::create('heks_kobo_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('service_name')->index();
            $table->string('table_name')->index();
            $table->text('kobo_field');
            $table->string('column_name');
            $table->timestamps();

            $table->unique(['service_name', 'column_name']);
        });

        foreach ([
            'heks_main_kobo_records',
            'heks_followups_kobo_records',
            'heks_boq_kobo_records',
            'heks_followup_boq_kobo_records',
        ] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->foreignId('heks_beneficiary_id')->nullable()->constrained('heks_beneficiaries')->nullOnDelete();
                $table->foreignId('heks_follow_up_id')->nullable()->constrained('heks_follow_ups')->nullOnDelete();
                $table->foreignId('kobo_rest_submission_id')->nullable()->constrained('kobo_rest_submissions')->nullOnDelete();
                $table->string('service_name')->index();
                $table->string('submission_uuid')->nullable()->unique();
                $table->timestamp('received_at')->nullable()->index();
                $table->timestamp('synced_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heks_followup_boq_kobo_records');
        Schema::dropIfExists('heks_boq_kobo_records');
        Schema::dropIfExists('heks_followups_kobo_records');
        Schema::dropIfExists('heks_main_kobo_records');
        Schema::dropIfExists('heks_kobo_field_mappings');
    }
};
