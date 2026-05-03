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
        Schema::table('login_logs', function (Blueprint $table) {
            $table->string('browser')->nullable()->after('user_agent');
            $table->string('device')->nullable()->after('browser');
            $table->string('platform')->nullable()->after('device');

            $table->boolean('is_suspicious')->default(false)->after('is_success');
            $table->string('suspicious_reason')->nullable()->after('is_suspicious');

            $table->unsignedInteger('failed_attempts_from_ip')->default(0)->after('suspicious_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('login_logs', function (Blueprint $table) {
            //
        });
    }
};
