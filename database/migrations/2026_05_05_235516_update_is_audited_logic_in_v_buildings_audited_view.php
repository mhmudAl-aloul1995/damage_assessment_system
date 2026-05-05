<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $viewName = 'v_buildings_audited';

    public function up(): void
    {
        $database = DB::getDatabaseName();

        $row = DB::selectOne("SHOW CREATE VIEW `{$this->viewName}`");
        $createViewSql = $row->{'Create View'} ?? null;

        if (! $createViewSql) {
            throw new RuntimeException("Cannot read CREATE VIEW for {$this->viewName}");
        }

        $newLogic = "
CASE
    WHEN EXISTS (
        SELECT 1
        FROM `{$database}`.`building_statuses` `bs`
        INNER JOIN `{$database}`.`assessment_statuses` `ast`
            ON `ast`.`id` = `bs`.`status_id`
        WHERE `bs`.`building_id` = `b`.`objectid`
          AND `bs`.`status_id` IS NOT NULL
          AND LOWER(TRIM(`ast`.`name`)) NOT IN (
              'pending',
              'assigned',
              'assignedto',
              'assigned_to',
              'assigned_to_engineer',
              'assigned_to_lawyer'
          )
    )
    THEN 1
    ELSE 0
END AS `is_audited`";

        // يستبدل أي CASE قديم ينتهي بـ AS `is_audited`
        $pattern = '/CASE\s+WHEN\s+.*?\s+END\s+AS\s+`is_audited`/is';

        $updatedSql = preg_replace($pattern, $newLogic, $createViewSql, 1, $count);

        if ($count === 0) {
            throw new RuntimeException('Could not find is_audited CASE expression in view SQL.');
        }

        $updatedSql = preg_replace(
            '/^CREATE\s+.*?\s+VIEW/i',
            'CREATE OR REPLACE VIEW',
            $updatedSql
        );

        DB::statement($updatedSql);
    }

    public function down(): void
    {
        $row = DB::selectOne("SHOW CREATE VIEW `{$this->viewName}`");
        $createViewSql = $row->{'Create View'} ?? null;

        if (! $createViewSql) {
            throw new RuntimeException("Cannot read CREATE VIEW for {$this->viewName}");
        }

        $oldLogic = "
CASE
    WHEN `audit_meta`.`global_id` IS NULL THEN 0
    ELSE 1
END AS `is_audited`";

        $pattern = '/CASE\s+WHEN\s+EXISTS\s*\(.*?END\s+AS\s+`is_audited`/is';

        $updatedSql = preg_replace($pattern, $oldLogic, $createViewSql, 1, $count);

        if ($count === 0) {
            throw new RuntimeException('Could not restore old is_audited logic.');
        }

        $updatedSql = preg_replace(
            '/^CREATE\s+.*?\s+VIEW/i',
            'CREATE OR REPLACE VIEW',
            $updatedSql
        );

        DB::statement($updatedSql);
    }
};