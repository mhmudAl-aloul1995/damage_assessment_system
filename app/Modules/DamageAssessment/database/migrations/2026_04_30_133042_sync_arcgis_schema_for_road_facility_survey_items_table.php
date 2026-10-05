<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('road_facility_survey_items')
            && Schema::hasColumn('road_facility_survey_items', 'objectid')
            && Schema::hasColumn('road_facility_survey_items', 'parentglobalid')
            && Schema::hasColumn('road_facility_survey_items', 'raw_payload')
        ) {
            return;
        }

        if (! Schema::hasTable('road_facility_survey_items')) {
            Schema::create('road_facility_survey_items', function (Blueprint $table) {
                $table->id();

                if (! Schema::hasColumn('road_facility_survey_items', 'objectid')) {
                    $table->unsignedBigInteger('objectid')->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'globalid')) {
                    $table->string('globalid', 50)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'item_required')) {
                    $table->string('item_required', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'description')) {
                    $table->string('description', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'unit_001')) {
                    $table->string('unit_001', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'quantity_001')) {
                    $table->integer('quantity_001')->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'other_comments')) {
                    $table->string('other_comments', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'parentglobalid')) {
                    $table->string('parentglobalid', 50)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'creationdate')) {
                    $table->dateTime('creationdate')->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'creator')) {
                    $table->string('creator', 128)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'editdate')) {
                    $table->dateTime('editdate')->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'editor')) {
                    $table->string('editor', 128)->nullable();
                }

                if (! Schema::hasColumn('road_facility_survey_items', 'raw_payload')) {
                    $table->longText('raw_payload')->nullable();
                }

                $table->timestamps();
            });
        } else {
            Schema::table('road_facility_survey_items', function (Blueprint $table) {
                if (! Schema::hasColumn('road_facility_survey_items', 'objectid')) {
                    $table->unsignedBigInteger('objectid')->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'globalid')) {
                    $table->string('globalid', 50)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'item_required')) {
                    $table->string('item_required', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'description')) {
                    $table->string('description', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'unit_001')) {
                    $table->string('unit_001', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'quantity_001')) {
                    $table->integer('quantity_001')->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'other_comments')) {
                    $table->string('other_comments', 255)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'parentglobalid')) {
                    $table->string('parentglobalid', 50)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'creationdate')) {
                    $table->dateTime('creationdate')->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'creator')) {
                    $table->string('creator', 128)->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'editdate')) {
                    $table->dateTime('editdate')->nullable();
                }
                if (! Schema::hasColumn('road_facility_survey_items', 'editor')) {
                    $table->string('editor', 128)->nullable();
                }

                if (! Schema::hasColumn('road_facility_survey_items', 'raw_payload')) {
                    $table->longText('raw_payload')->nullable();
                }
            });
        }

        Schema::table('road_facility_survey_items', function (Blueprint $table) {
            try {
                if (Schema::hasColumn('road_facility_survey_items', 'objectid')) {
                    $table->index('objectid', 'idx_road_facility_survey_items_objectid');
                }
            } catch (Throwable $e) {
            }

            try {
                if (Schema::hasColumn('road_facility_survey_items', 'globalid')) {
                    $table->index('globalid', 'idx_road_facility_survey_items_globalid');
                }
            } catch (Throwable $e) {
            }

            try {
                if (Schema::hasColumn('road_facility_survey_items', 'parentglobalid')) {
                    $table->index('parentglobalid', 'idx_road_facility_survey_items_parentglobalid');
                }
            } catch (Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        // Safe rollback: do not drop table automatically.
    }
};
