<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_decisions', function (Blueprint $table): void {
            $table->foreignId('parent_decision_id')
                ->nullable()
                ->after('id')
                ->constrained('committee_decisions')
                ->nullOnDelete();
            $table->unsignedInteger('decision_round')->default(1)->after('parent_decision_id');
            $table->string('decision_source')->default('regular')->after('decision_round');

            $table->index(['parent_decision_id', 'decision_source'], 'committee_decisions_parent_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('committee_decisions', function (Blueprint $table): void {
            $table->dropIndex('committee_decisions_parent_source_index');
            $table->dropConstrainedForeignId('parent_decision_id');
            $table->dropColumn(['decision_round', 'decision_source']);
        });
    }
};
