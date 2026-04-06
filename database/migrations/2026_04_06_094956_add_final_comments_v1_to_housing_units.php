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
        Schema::table('housing_units', function (Blueprint $table) {
            $table->text('final_comments_v1')->nullable()->after('id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('housing_units', function (Blueprint $table) {
            $table->dropColumn('final_comments_v1');

        });
    }
};
