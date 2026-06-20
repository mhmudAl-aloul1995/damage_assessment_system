<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->dropEmptyPartialTable('damage_assessment_borrower_resident_households');
        $this->dropEmptyPartialTable('damage_assessment_borrower_attachments');
        $this->dropEmptyPartialTable('damage_assessment_borrower_boq_items');
        $this->dropEmptyPartialTable('damage_assessment_borrower_boq_catalog_items');

        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            if (! Schema::hasColumn('damage_assessment_borrowers', 'location_latitude')) {
                $table->decimal('location_latitude', 10, 7)->nullable()->after('surveyed_at');
            }

            if (! Schema::hasColumn('damage_assessment_borrowers', 'location_longitude')) {
                $table->decimal('location_longitude', 10, 7)->nullable()->after('location_latitude');
            }

            if (! Schema::hasColumn('damage_assessment_borrowers', 'location_altitude')) {
                $table->decimal('location_altitude', 10, 2)->nullable()->after('location_longitude');
            }

            if (! Schema::hasColumn('damage_assessment_borrowers', 'location_precision')) {
                $table->decimal('location_precision', 10, 2)->nullable()->after('location_altitude');
            }

            if (! Schema::hasColumn('damage_assessment_borrowers', 'boq_total_usd')) {
                $table->decimal('boq_total_usd', 14, 2)->default(0)->after('risk_reasons');
            }

            if (! Schema::hasColumn('damage_assessment_borrowers', 'attachments_count')) {
                $table->unsignedSmallInteger('attachments_count')->default(0)->after('boq_total_usd');
            }
        });

        if (! Schema::hasTable('damage_assessment_borrower_boq_catalog_items')) {
            Schema::create('damage_assessment_borrower_boq_catalog_items', function (Blueprint $table) {
                $table->id();
                $table->string('item_code')->nullable()->unique('brw_boq_catalog_item_code_unique');
                $table->text('source_column')->nullable();
                $table->string('source_key', 40)->nullable()->index('brw_boq_catalog_source_key_idx');
                $table->text('description');
                $table->string('normalized_description')->index('brw_boq_catalog_norm_desc_idx');
                $table->string('unit', 50)->nullable();
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->string('category')->nullable()->index('brw_boq_catalog_category_idx');
                $table->string('source_sheet')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('damage_assessment_borrower_boq_items')) {
            Schema::create('damage_assessment_borrower_boq_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('damage_assessment_borrower_id');
                $table->foreignId('catalog_item_id')->nullable();
                $table->text('source_column');
                $table->string('source_key', 40)->index('brw_boq_items_source_key_idx');
                $table->string('item_code')->nullable();
                $table->text('description');
                $table->string('unit', 50)->nullable();
                $table->decimal('unit_price', 14, 2)->default(0);
                $table->decimal('quantity', 14, 2)->default(0);
                $table->decimal('total_price', 14, 2)->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['damage_assessment_borrower_id', 'source_key'], 'borrower_boq_source_unique');
                $table->foreign('damage_assessment_borrower_id', 'brw_boq_items_borrower_fk')
                    ->references('id')
                    ->on('damage_assessment_borrowers')
                    ->cascadeOnDelete();
                $table->foreign('catalog_item_id', 'brw_boq_items_catalog_fk')
                    ->references('id')
                    ->on('damage_assessment_borrower_boq_catalog_items')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('damage_assessment_borrower_attachments')) {
            Schema::create('damage_assessment_borrower_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('damage_assessment_borrower_id');
                $table->string('filename')->nullable();
                $table->text('url')->nullable();
                $table->unsignedInteger('source_index')->nullable();
                $table->timestamps();

                $table->unique(['damage_assessment_borrower_id', 'source_index'], 'borrower_attachment_index_unique');
                $table->foreign('damage_assessment_borrower_id', 'brw_attachments_borrower_fk')
                    ->references('id')
                    ->on('damage_assessment_borrowers')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('damage_assessment_borrower_resident_households')) {
            Schema::create('damage_assessment_borrower_resident_households', function (Blueprint $table) {
                $table->id();
                $table->foreignId('damage_assessment_borrower_id');
                $table->string('head_name');
                $table->string('id_number')->nullable();
                $table->unsignedSmallInteger('members_count')->nullable();
                $table->string('phone')->nullable();
                $table->string('employment_status')->nullable()->index('brw_households_employment_idx');
                $table->unsignedInteger('source_index')->nullable();
                $table->timestamps();

                $table->unique(['damage_assessment_borrower_id', 'source_index'], 'borrower_household_index_unique');
                $table->foreign('damage_assessment_borrower_id', 'brw_households_borrower_fk')
                    ->references('id')
                    ->on('damage_assessment_borrowers')
                    ->cascadeOnDelete();
            });
        }
    }

    private function dropEmptyPartialTable(string $table): void
    {
        if (Schema::hasTable($table) && DB::table($table)->count() === 0) {
            Schema::drop($table);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damage_assessment_borrower_resident_households');
        Schema::dropIfExists('damage_assessment_borrower_attachments');
        Schema::dropIfExists('damage_assessment_borrower_boq_items');
        Schema::dropIfExists('damage_assessment_borrower_boq_catalog_items');

        Schema::table('damage_assessment_borrowers', function (Blueprint $table) {
            $table->dropColumn([
                'location_latitude',
                'location_longitude',
                'location_altitude',
                'location_precision',
                'boq_total_usd',
                'attachments_count',
            ]);
        });
    }
};
