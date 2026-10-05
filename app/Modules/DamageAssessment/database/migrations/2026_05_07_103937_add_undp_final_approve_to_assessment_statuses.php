<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('assessment_statuses')) {
            return;
        }

        $finalApprovalOrder = (int) (DB::table('assessment_statuses')
            ->where('name', 'final_approval')
            ->value('order_step') ?? 9);

        $maxOrderStep = (int) (DB::table('assessment_statuses')->max('order_step') ?? $finalApprovalOrder);

        $statusData = [
            'label_en' => 'UNDP Final Approve',
            'label_ar' => 'اعتماد نهائي UNDP',
            'stage' => 'system',
            'order_step' => max($finalApprovalOrder + 1, $maxOrderStep + 1),
            'updated_at' => now(),
        ];

        $exists = DB::table('assessment_statuses')
            ->where('name', 'undp_final_approve')
            ->exists();

        if ($exists) {
            DB::table('assessment_statuses')
                ->where('name', 'undp_final_approve')
                ->update($statusData);

            return;
        }

        DB::table('assessment_statuses')->insert([
            'name' => 'undp_final_approve',
            ...$statusData,
            'created_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('assessment_statuses')) {
            return;
        }

        DB::table('assessment_statuses')
            ->where('name', 'undp_final_approve')
            ->delete();
    }
};
