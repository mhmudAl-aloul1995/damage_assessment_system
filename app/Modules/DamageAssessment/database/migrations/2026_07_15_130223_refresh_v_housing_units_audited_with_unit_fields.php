<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->refreshView();
    }

    public function down(): void
    {
        $this->refreshView([
            'unit_governorate',
            'unit_municipalitie',
            'unit_neighborhood',
            'unit_building_name',
        ]);
    }

    /**
     * @param  array<int, string>  $excludedColumns
     */
    private function refreshView(array $excludedColumns = []): void
    {
        DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');

        $columns = collect(Schema::getColumnListing('housing_units'))
            ->reject(fn (string $column): bool => in_array($column, $excludedColumns, true))
            ->values();

        $selectColumns = [];
        $editColumns = [];

        foreach ($columns as $column) {
            $quoted = "`{$column}`";

            $selectColumns[] = "COALESCE(e.{$quoted}, h.{$quoted}) AS {$quoted}";

            $field = str_replace("'", "''", $column);
            $editColumns[] = "MAX(CASE WHEN x.field_name = '{$field}' THEN x.field_value END) AS {$quoted}";
        }

        $selectSql = implode(",\n                ", $selectColumns);
        $editSql = implode(",\n                    ", $editColumns);

        DB::unprepared("
            CREATE VIEW v_housing_units_audited AS
            SELECT
                {$selectSql},

                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM housing_statuses hs
                        INNER JOIN assessment_statuses ast
                            ON ast.id = hs.status_id
                        WHERE hs.housing_id = h.objectid
                          AND hs.status_id IS NOT NULL
                          AND LOWER(TRIM(ast.name)) IN (
                              'need_review',
                              'accepted_by_engineer',
                              'rejected_by_engineer'
                          )
                    )
                    THEN 1
                    ELSE 0
                END AS is_audited,
                audit_meta.last_audit_user_id,
                audit_meta.last_audit_at,
                status_meta.last_status_user_id,
                status_meta.last_status_at

            FROM housing_units h

            LEFT JOIN (
                SELECT
                    x.global_id,
                    {$editSql}
                FROM (
                    SELECT ea.global_id, ea.field_name, ea.field_value
                    FROM edit_assessments ea
                    INNER JOIN (
                        SELECT global_id, field_name, MAX(id) AS max_id
                        FROM edit_assessments
                        WHERE type = 'housing_table'
                        GROUP BY global_id, field_name
                    ) latest ON latest.max_id = ea.id
                    WHERE ea.type = 'housing_table'
                ) x
                GROUP BY x.global_id
            ) e ON e.global_id = h.globalid

            LEFT JOIN (
                SELECT
                    ea.global_id,
                    ea.user_id AS last_audit_user_id,
                    ea.updated_at AS last_audit_at
                FROM edit_assessments ea
                INNER JOIN (
                    SELECT global_id, MAX(id) AS max_id
                    FROM edit_assessments
                    WHERE type = 'housing_table'
                    GROUP BY global_id
                ) latest ON latest.max_id = ea.id
                WHERE ea.type = 'housing_table'
            ) audit_meta ON audit_meta.global_id = h.globalid

            LEFT JOIN (
                SELECT
                    ea.global_id,
                    ea.user_id AS last_status_user_id,
                    ea.updated_at AS last_status_at
                FROM edit_assessments ea
                INNER JOIN (
                    SELECT global_id, MAX(id) AS max_id
                    FROM edit_assessments
                    WHERE type = 'housing_table'
                      AND field_name = 'field_status'
                    GROUP BY global_id
                ) latest ON latest.max_id = ea.id
                WHERE ea.type = 'housing_table'
                  AND ea.field_name = 'field_status'
            ) status_meta ON status_meta.global_id = h.globalid
        ");
    }
};
