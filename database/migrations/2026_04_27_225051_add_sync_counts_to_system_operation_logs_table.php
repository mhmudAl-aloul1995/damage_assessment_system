<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_operation_logs', function (Blueprint $table) {
            $table->integer('inserted')->default(0)->after('total_records');
            $table->integer('updated')->default(0)->after('inserted');
            $table->integer('skipped')->default(0)->after('updated');
        });
    }

    public function down(): void
    {
        Schema::table('system_operation_logs', function (Blueprint $table) {
            $table->dropColumn(['inserted', 'updated', 'skipped']);
        });
    }
};