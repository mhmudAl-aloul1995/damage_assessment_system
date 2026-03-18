<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AssessmentsSqlSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/sql/assessment.sql');

        if (! File::exists($path)) {
            throw new \Exception("SQL file not found: {$path}");
        }

        $sql = File::get($path);

        if (blank($sql)) {
            throw new \Exception("SQL file is empty: {$path}");
        }

        DB::unprepared($sql);
    }
}