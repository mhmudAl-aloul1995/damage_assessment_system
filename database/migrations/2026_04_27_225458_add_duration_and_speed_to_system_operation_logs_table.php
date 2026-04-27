<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_operation_logs', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')->nullable()->after('skipped');
            $table->decimal('records_per_second', 10, 2)->nullable()->after('duration_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_operation_logs', function (Blueprint $table) {
            //
        });
    }
};
