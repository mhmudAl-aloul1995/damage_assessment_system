<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kobo_rest_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('kobo_rest_submissions', 'source_record_key')) {
                $table->string('source_record_key')->nullable()->after('submission_uuid')->index('kobo_rest_source_key_idx');
            }
        });

        $this->replaceGlobalSubmissionUuidUnique();

        Schema::table('heks_imports', function (Blueprint $table): void {
            if (! Schema::hasColumn('heks_imports', 'status')) {
                $table->string('status')->default('synced')->after('type')->index('heks_imports_status_idx');
            }

            if (! Schema::hasColumn('heks_imports', 'error_report')) {
                $table->json('error_report')->nullable()->after('summary');
            }
        });

        Schema::table('heks_kobo_field_mappings', function (Blueprint $table): void {
            if (! Schema::hasColumn('heks_kobo_field_mappings', 'data_type')) {
                $table->string('data_type')->nullable()->after('display_label');
            }

            if (! Schema::hasColumn('heks_kobo_field_mappings', 'mapping_status')) {
                $table->string('mapping_status')->default('wide_only')->after('data_type')->index('heks_map_status_idx');
            }

            if (! Schema::hasColumn('heks_kobo_field_mappings', 'confidence')) {
                $table->string('confidence')->default('low')->after('mapping_status');
            }

            if (! Schema::hasColumn('heks_kobo_field_mappings', 'notes')) {
                $table->text('notes')->nullable()->after('confidence');
            }
        });

        $this->addUniqueIndex('heks_kobo_field_mappings', ['service_name', 'table_name', 'kobo_field'], 'heks_map_service_table_field_unique');

        Schema::table('heks_follow_ups', function (Blueprint $table): void {
            if (! Schema::hasColumn('heks_follow_ups', 'submission_uuid')) {
                $table->string('submission_uuid')->nullable()->after('code')->index('heks_followups_uuid_idx');
            }

            if (! Schema::hasColumn('heks_follow_ups', 'source_record_key')) {
                $table->string('source_record_key')->nullable()->after('submission_uuid')->index('heks_followups_source_key_idx');
            }
        });

        Schema::table('heks_boq_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('heks_boq_items', 'submission_uuid')) {
                $table->string('submission_uuid')->nullable()->after('source')->index('heks_boq_items_uuid_idx');
            }

            if (! Schema::hasColumn('heks_boq_items', 'source_record_key')) {
                $table->string('source_record_key')->nullable()->after('submission_uuid')->index('heks_boq_items_source_key_idx');
            }
        });

        Schema::table('heks_attachments', function (Blueprint $table): void {
            if (! Schema::hasColumn('heks_attachments', 'source_record_key')) {
                $table->string('source_record_key')->nullable()->after('source')->index('heks_attachments_source_key_idx');
            }
        });

        foreach ($this->wideTables() as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'source_record_key')) {
                    $table->string('source_record_key')->nullable()->after('submission_uuid')->index("{$tableName}_src_key_idx");
                }

                if (! Schema::hasColumn($tableName, 'raw_data')) {
                    $table->json('raw_data')->nullable()->after('source_record_key');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->wideTables() as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'raw_data')) {
                    $table->dropColumn('raw_data');
                }

                if (Schema::hasColumn($tableName, 'source_record_key')) {
                    $table->dropIndex("{$tableName}_src_key_idx");
                    $table->dropColumn('source_record_key');
                }
            });
        }

        Schema::table('heks_attachments', function (Blueprint $table): void {
            if (Schema::hasColumn('heks_attachments', 'source_record_key')) {
                $table->dropIndex('heks_attachments_source_key_idx');
                $table->dropColumn('source_record_key');
            }
        });

        Schema::table('heks_boq_items', function (Blueprint $table): void {
            foreach (['source_record_key' => 'heks_boq_items_source_key_idx', 'submission_uuid' => 'heks_boq_items_uuid_idx'] as $column => $index) {
                if (Schema::hasColumn('heks_boq_items', $column)) {
                    $table->dropIndex($index);
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('heks_follow_ups', function (Blueprint $table): void {
            foreach (['source_record_key' => 'heks_followups_source_key_idx', 'submission_uuid' => 'heks_followups_uuid_idx'] as $column => $index) {
                if (Schema::hasColumn('heks_follow_ups', $column)) {
                    $table->dropIndex($index);
                    $table->dropColumn($column);
                }
            }
        });

        $this->dropIndex('heks_kobo_field_mappings', 'heks_map_service_table_field_unique');

        Schema::table('heks_kobo_field_mappings', function (Blueprint $table): void {
            foreach (['notes', 'confidence', 'mapping_status', 'data_type'] as $column) {
                if (Schema::hasColumn('heks_kobo_field_mappings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('heks_imports', function (Blueprint $table): void {
            if (Schema::hasColumn('heks_imports', 'error_report')) {
                $table->dropColumn('error_report');
            }

            if (Schema::hasColumn('heks_imports', 'status')) {
                $table->dropIndex('heks_imports_status_idx');
                $table->dropColumn('status');
            }
        });

        $this->dropIndex('kobo_rest_submissions', 'kobo_rest_service_uuid_unique');

        Schema::table('kobo_rest_submissions', function (Blueprint $table): void {
            if (Schema::hasColumn('kobo_rest_submissions', 'source_record_key')) {
                $table->dropIndex('kobo_rest_source_key_idx');
                $table->dropColumn('source_record_key');
            }
        });
    }

    private function replaceGlobalSubmissionUuidUnique(): void
    {
        $this->dropIndex('kobo_rest_submissions', 'kobo_rest_submissions_submission_uuid_unique');
        $this->addUniqueIndex('kobo_rest_submissions', ['service_name', 'submission_uuid'], 'kobo_rest_service_uuid_unique');
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function addUniqueIndex(string $table, array $columns, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($columns, $name): void {
                $table->unique($columns, $name);
            });
        } catch (\Throwable) {
            //
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($name): void {
                $table->dropIndex($name);
            });
        } catch (\Throwable) {
            try {
                Schema::table($table, function (Blueprint $table) use ($name): void {
                    $table->dropUnique($name);
                });
            } catch (\Throwable) {
                //
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function wideTables(): array
    {
        return [
            'heks_main_kobo_records',
            'heks_followups_kobo_records',
            'heks_boq_kobo_records',
            'heks_followup_boq_kobo_records',
        ];
    }
};
