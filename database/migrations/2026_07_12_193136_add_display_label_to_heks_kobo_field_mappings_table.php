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
        Schema::table('heks_kobo_field_mappings', function (Blueprint $table) {
            $table->text('display_label')->nullable()->after('column_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('heks_kobo_field_mappings', function (Blueprint $table) {
            $table->dropColumn('display_label');
        });
    }
};
