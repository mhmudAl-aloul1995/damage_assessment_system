<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CsoAuditLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(\App\services\CsoAuditService::class)->importLegacyEdits();
    }
}
