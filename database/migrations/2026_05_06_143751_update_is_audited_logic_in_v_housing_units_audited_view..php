<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $viewName = 'v_housing_units_audited';

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
        FROM `{$database}`.`housing_statuses` `hs`
        INNER JOIN `{$database}`.`assessment_statuses` `ast`
            ON `ast`.`id` = `hs`.`status_id`
        WHERE `hs`.`housing_id` = `h`.`objectid`
          AND `hs`.`status_id` IS NOT NULL
          AND LOWER(TRIM(`ast`.`name`)) IN (
              'need_review',
              'accepted_by_engineer',
              'rejected_by_engineer'
          )
    )
    THEN 1
    ELSE 0
END AS `is_audited`";

        $pattern = '/CASE\s+WHEN\s+.*?\s+END\s+AS\s+`is_audited`/is';

        $updatedSql = preg_replace($pattern, $newLogic, $createViewSql, 1, $count);

        if ($count === 0) {
            throw new RuntimeException('Could not find is_audited CASE expression in housing view SQL.');
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
            throw new RuntimeException('Could not restore old housing is_audited logic.');
        }

        $updatedSql = preg_replace(
            '/^CREATE\s+.*?\s+VIEW/i',
            'CREATE OR REPLACE VIEW',
            $updatedSql
        );

        DB::statement($updatedSql);
    }
};