<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_buildings_audited');

        $database = DB::getDatabaseName();

        $skipColumns = [
          /*   'id',
            'objectid',
            'globalid',
            'arcgis_hash',
            'arcgis_synced_at',
            'created_at',
            'updated_at', */
        ];

        $columns = Schema::getColumnListing('buildings');

        $selectColumns = [];
        $editColumns = [];

        foreach ($columns as $column) {
            $quoted = "`{$column}`";

            if (in_array($column, $skipColumns, true)) {
                $selectColumns[] = "b.{$quoted} AS {$quoted}";
            } else {
                $selectColumns[] = "COALESCE(e.{$quoted}, b.{$quoted}) AS {$quoted}";

                $field = str_replace("'", "''", $column);
                $editColumns[] = "MAX(CASE WHEN x.field_name = '{$field}' THEN x.field_value END) AS {$quoted}";
            }
        }

        $selectSql = implode(",\n            ", $selectColumns);
        $editSql = implode(",\n                ", $editColumns);

        $sql = "
            CREATE VIEW v_buildings_audited AS
            SELECT
                {$selectSql},

                s.id AS audit_status_id,
                s.name AS audit_status_name,
                s.label_en AS audit_status_label_en,
                s.label_ar AS audit_status_label_ar,
                s.stage AS audit_status_stage,
                s.order_step AS audit_status_order_step,

                CASE WHEN audit_meta.global_id IS NULL THEN 0 ELSE 1 END AS is_audited,
                audit_meta.last_audit_user_id,
                audit_meta.last_audit_at,
                status_meta.last_status_user_id,
                status_meta.last_status_at

            FROM buildings b

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
                        WHERE type = 'building_table'
                        GROUP BY global_id, field_name
                    ) latest ON latest.max_id = ea.id
                    WHERE ea.type = 'building_table'
                ) x
                GROUP BY x.global_id
            ) e ON e.global_id = b.globalid

            LEFT JOIN (
                SELECT
                    ea.global_id,
                    ea.user_id AS last_audit_user_id,
                    ea.updated_at AS last_audit_at
                FROM edit_assessments ea
                INNER JOIN (
                    SELECT global_id, MAX(id) AS max_id
                    FROM edit_assessments
                    WHERE type = 'building_table'
                    GROUP BY global_id
                ) latest ON latest.max_id = ea.id
                WHERE ea.type = 'building_table'
            ) audit_meta ON audit_meta.global_id = b.globalid

            LEFT JOIN (
                SELECT
                    ea.global_id,
                    ea.user_id AS last_status_user_id,
                    ea.updated_at AS last_status_at
                FROM edit_assessments ea
                INNER JOIN (
                    SELECT global_id, MAX(id) AS max_id
                    FROM edit_assessments
                    WHERE type = 'building_table'
                      AND field_name = 'field_status'
                    GROUP BY global_id
                ) latest ON latest.max_id = ea.id
                WHERE ea.type = 'building_table'
                  AND ea.field_name = 'field_status'
            ) status_meta ON status_meta.global_id = b.globalid

            LEFT JOIN assessment_statuses s
                ON LOWER(TRIM(s.name)) = LOWER(TRIM(COALESCE(e.`field_status`, b.`field_status`)))
        ";

        DB::unprepared($sql);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_buildings_audited');
    }
};