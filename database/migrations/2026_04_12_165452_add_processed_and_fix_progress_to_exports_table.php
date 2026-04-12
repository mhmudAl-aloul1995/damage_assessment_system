<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            if (!Schema::hasColumn('exports', 'progress')) {
                $table->unsignedInteger('progress')->default(0)->after('status');
            } else {
                $table->unsignedInteger('progress')->default(0)->change();
            }

            if (!Schema::hasColumn('exports', 'processed')) {
                $table->unsignedBigInteger('processed')->default(0)->after('progress');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exports', function (Blueprint $table) {
            if (Schema::hasColumn('exports', 'processed')) {
                $table->dropColumn('processed');
            }
        });
    }
};