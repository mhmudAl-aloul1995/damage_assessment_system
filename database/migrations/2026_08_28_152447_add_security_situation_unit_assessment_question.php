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
        $questions = [
            [
                'name' => 'security_situation_unit',
                'label' => '7.3 Security Situation unit',
                'hint' => 'هل يوجد عائق يمنع عملية الحصر',
            ],
            [
                'name' => 'security_unit_info',
                'label' => '7.3.1 security information',
                'hint' => 'ما هو العائق',
            ],
        ];

        foreach ($questions as $question) {
            $values = [
                'label' => $question['label'],
                'hint' => $question['hint'],
            ];

            if (Schema::hasColumn('assessments', 'type')) {
                $values['type'] = '0';
            }

            DB::table('assessments')->updateOrInsert(
                ['name' => $question['name']],
                $values
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('assessments')
            ->whereIn('name', ['security_situation_unit', 'security_unit_info'])
            ->delete();
    }
};
