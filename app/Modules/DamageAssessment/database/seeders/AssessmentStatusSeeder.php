<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssessmentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [

            [
                'name' => 'pending',
                'label_en' => 'Pending',
                'label_ar' => 'استمارة لم يتم تدقيقها في الموقع',
                'stage' => 'system',
                'order_step' => 1,
            ],

            [
                'name' => 'assigned_to_engineer',
                'label_en' => 'Assigned To Engineer',
                'label_ar' => 'الاستمارة تم تحويلها للمهندس للتدقيق',
                'stage' => 'engineer',
                'order_step' => 2,
            ],

            [
                'name' => 'rejected_by_engineer',
                'label_en' => 'Rejected By Engineer',
                'label_ar' => 'مرفوضة بواسطة المهندس',
                'stage' => 'engineer',
                'order_step' => 3,
            ],

            [
                'name' => 'accepted_by_engineer',
                'label_en' => 'Accepted By Engineer',
                'label_ar' => 'مقبولة بواسطة المهندس',
                'stage' => 'engineer',
                'order_step' => 4,
            ],

            [
                'name' => 'need_review',
                'label_en' => 'Need Review',
                'label_ar' => 'يحتاج الى مراجعة',
                'stage' => 'engineer',
                'order_step' => 5,
            ],

            [
                'name' => 'assigned_to_lawyer',
                'label_en' => 'Assigned To Lawyer',
                'label_ar' => 'الاستمارة تم تحويلها للمحامي للتدقيق',
                'stage' => 'lawyer',
                'order_step' => 6,
            ],

            [
                'name' => 'legal_notes',
                'label_en' => 'Legal Notes',
                'label_ar' => 'ملاحظات قانونية',
                'stage' => 'lawyer',
                'order_step' => 7,
            ],

            [
                'name' => 'accepted_by_lawyer',
                'label_en' => 'Accepted By Lawyer',
                'label_ar' => 'مقبولة بواسطة المحامي',
                'stage' => 'lawyer',
                'order_step' => 8,
            ],

            [
                'name' => 'final_approval',
                'label_en' => 'Final Approval',
                'label_ar' => 'مقبولة نهائياً (من Team Leader)',
                'stage' => 'team_leader',
                'order_step' => 9,
            ],

            [
                'name' => 'final_reject',
                'label_en' => 'Final Reject',
                'label_ar' => 'مرفوضة نهائياً (من Team Leader)',
                'stage' => 'team_leader',
                'order_step' => 10,
            ],

            [
                'name' => 'undp_final_approve',
                'label_en' => 'UNDP Final Approve',
                'label_ar' => 'اعتماد نهائي UNDP',
                'stage' => 'system',
                'order_step' => 11,
            ],

        ];

        foreach ($statuses as &$status) {
            $status['created_at'] = now();
            $status['updated_at'] = now();
        }

        foreach ($statuses as $status) {
            $values = [
                'label_en' => $status['label_en'],
                'label_ar' => $status['label_ar'],
                'stage' => $status['stage'],
                'order_step' => $status['order_step'],
                'updated_at' => $status['updated_at'],
            ];

            if (DB::table('assessment_statuses')->where('name', $status['name'])->exists()) {
                DB::table('assessment_statuses')
                    ->where('name', $status['name'])
                    ->update($values);

                continue;
            }

            DB::table('assessment_statuses')->insert([
                'name' => $status['name'],
                ...$values,
                'created_at' => $status['created_at'],
            ]);
        }
    }
}
