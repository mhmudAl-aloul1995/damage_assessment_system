<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->string('name_en')->nullable()->after('name');

            $table->string('id_no')->nullable()->after('name_en');
            $table->unique('id_no');
            $table->enum('contract_type', [
                'phc',
                'undp',
                'mopwh',
                'pef',
            ])->nullable()->after('id_no');

        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'id_no', 'contract_type']);
        });
    }
};