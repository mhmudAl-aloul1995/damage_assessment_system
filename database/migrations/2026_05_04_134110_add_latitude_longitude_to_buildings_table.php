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
        Schema::table('buildings', function (Blueprint $table) {
            if (! Schema::hasColumn('buildings', 'latitude')) {
                $table->double('latitude')->nullable();
            }

            if (! Schema::hasColumn('buildings', 'longitude')) {
                $table->double('longitude')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            if (Schema::hasColumn('buildings', 'latitude')) {
                $table->dropColumn('latitude');
            }

            if (Schema::hasColumn('buildings', 'longitude')) {
                $table->dropColumn('longitude');
            }
        });
    }
};
