<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createCacheTable('audited_buildings', 'buildings');
        $this->createCacheTable('audited_housing_units', 'housing_units');
    }

    public function down(): void
    {
        Schema::dropIfExists('audited_housing_units');
        Schema::dropIfExists('audited_buildings');
    }

    private function createCacheTable(string $cacheTable, string $sourceTable): void
    {
        if (Schema::hasTable($cacheTable)) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("CREATE TABLE `{$cacheTable}` LIKE `{$sourceTable}`");
            $this->addAuditColumns($cacheTable);

            return;
        }

        Schema::create($cacheTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('objectid')->nullable()->index();
            $table->text('globalid')->nullable();
            $table->text('parentglobalid')->nullable();
            $table->text('field_status')->nullable();
            $table->text('building_damage_status')->nullable();
            $table->text('unit_damage_status')->nullable();
            $table->text('submission_date')->nullable();
            $table->text('building_submit_date')->nullable();
            $table->text('governorate')->nullable();
            $table->text('neighborhood')->nullable();
            $table->text('assessment_obstacle')->nullable();
            $table->text('uxo_present')->nullable();
            $table->text('bodies_present')->nullable();
            $table->text('building_debris_exist')->nullable();
            $table->text('has_fire')->nullable();
            $table->text('unit_stripping')->nullable();
            $table->text('is_the_housing_unit_or_living_habitable')->nullable();
            $table->text('security_situation_unit')->nullable();
            $table->text('unit_support_needed')->nullable();
            $table->timestamp('editdate')->nullable();
            $table->timestamps();
        });

        $this->addAuditColumns($cacheTable);
    }

    private function addAuditColumns(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'is_audited')) {
                $table->boolean('is_audited')->default(false)->index();
            }

            if (! Schema::hasColumn($tableName, 'last_audit_user_id')) {
                $table->unsignedBigInteger('last_audit_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'last_audit_at')) {
                $table->timestamp('last_audit_at')->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'last_status_user_id')) {
                $table->unsignedBigInteger('last_status_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'last_status_at')) {
                $table->timestamp('last_status_at')->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'audit_status_id')) {
                $table->unsignedBigInteger('audit_status_id')->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'audit_status_name')) {
                $table->string('audit_status_name')->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'audit_status_label_en')) {
                $table->string('audit_status_label_en')->nullable();
            }

            if (! Schema::hasColumn($tableName, 'audit_status_label_ar')) {
                $table->string('audit_status_label_ar')->nullable();
            }

            if (! Schema::hasColumn($tableName, 'audit_status_stage')) {
                $table->string('audit_status_stage')->nullable()->index();
            }

            if (! Schema::hasColumn($tableName, 'audit_status_order_step')) {
                $table->integer('audit_status_order_step')->nullable()->index();
            }
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("CREATE INDEX IF NOT EXISTS {$tableName}_globalid_prefix_index ON `{$tableName}` (`globalid`(191))");
            DB::statement("CREATE INDEX IF NOT EXISTS {$tableName}_objectid_index ON `{$tableName}` (`objectid`)");
        }
    }
};
