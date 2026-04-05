<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {


            $table->text('assessment_obstacle_info_v1')
                ->nullable()
                ->after('assessment_obstacle');

        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {

            $table->dropColumn([
                'assessment_obstacle_info_v1'
            ]);

        });
    }
};