<?php

namespace Database\Seeders;

use App\Models\InfAuditStatus;
use Illuminate\Database\Seeder;

class InfAuditStatusesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['name' => 'assigned', 'label_en' => 'Assigned', 'label_ar' => 'مسند', 'order_step' => 1],
            ['name' => 'accepted', 'label_en' => 'Accepted', 'label_ar' => 'مقبول', 'order_step' => 2],
            ['name' => 'rejected', 'label_en' => 'Rejected', 'label_ar' => 'مرفوض', 'order_step' => 3],
            ['name' => 'need_review', 'label_en' => 'Need Review', 'label_ar' => 'بحاجة لمراجعة', 'order_step' => 4],
            ['name' => 'final_approval', 'label_en' => 'Final Approval', 'label_ar' => 'اعتماد نهائي', 'order_step' => 5],
        ];

        foreach ($statuses as $status) {
            InfAuditStatus::query()->updateOrCreate(
                ['name' => $status['name']],
                $status,
            );
        }
    }
}
