<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');

        $skipColumns = [
          /*   'id',
            'objectid',
            'globalid',
            'parentglobalid',
            'arcgis_hash',
            'arcgis_synced_at',
            'created_at',
            'updated_at', */
        ];

        $columns = Schema::getColumnListing('housing_units');

        $selectColumns = [];
        $editColumns = [];

        foreach ($columns as $column) {
            $quoted = "`{$column}`";

            if (in_array($column, $skipColumns, true)) {
                $selectColumns[] = "h.{$quoted} AS {$quoted}";
            } else {
                $selectColumns[] = "COALESCE(e.{$quoted}, h.{$quoted}) AS {$quoted}";

                $field = str_replace("'", "''", $column);
                $editColumns[] = "MAX(CASE WHEN x.field_name = '{$field}' THEN x.field_value END) AS {$quoted}";
            }
        }

        $selectSql = implode(",\n                ", $selectColumns);
        $editSql = implode(",\n                    ", $editColumns);

        $sql = "
            CREATE VIEW v_housing_units_audited AS
            SELECT
                {$selectSql},

                CASE WHEN audit_meta.global_id IS NULL THEN 0 ELSE 1 END AS is_audited,
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
        ";

        DB::unprepared($sql);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_housing_units_audited');
    }
};