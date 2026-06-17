<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_decision_signatures', function (Blueprint $table): void {
            if (! Schema::hasColumn('committee_decision_signatures', 'is_required')) {
                $table->boolean('is_required')->default(true)->after('committee_member_id');
            }

            if (! Schema::hasColumn('committee_decision_signatures', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('committee_decision_signatures', function (Blueprint $table): void {
            if (Schema::hasColumn('committee_decision_signatures', 'sort_order')) {
                $table->dropColumn('sort_order');
            }

            if (Schema::hasColumn('committee_decision_signatures', 'is_required')) {
                $table->dropColumn('is_required');
            }
        });
    }
};
