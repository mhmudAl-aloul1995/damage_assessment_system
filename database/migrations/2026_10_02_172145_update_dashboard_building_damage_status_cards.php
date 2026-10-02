<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('dashboard_cards') || ! Schema::hasTable('dashboard_card_items')) {
            return;
        }

        $buildingCardId = DB::table('dashboard_cards')
            ->where('key', 'buildings')
            ->value('id');

        if ($buildingCardId === null) {
            return;
        }

        $now = now();

        DB::table('dashboard_card_items')
            ->where('dashboard_card_id', $buildingCardId)
            ->where('key', 'completed')
            ->update([
                'is_active' => false,
                'updated_at' => $now,
            ]);

        DB::table('dashboard_card_items')->updateOrInsert(
            [
                'dashboard_card_id' => $buildingCardId,
                'key' => 'no_damage',
            ],
            [
                'title' => 'ui.damage_dashboard.no_damage',
                'source_bucket' => 'buildingStats',
                'stat_key' => 'no_damage',
                'icon' => 'ki-check-circle',
                'link_group' => 'buildings',
                'link_key' => 'no_damage',
                'calculation_type' => 'stat_key',
                'source_model' => null,
                'filter_field' => 'building_damage_status',
                'filter_operator' => '=',
                'filter_value' => 'no_damaged',
                'value_suffix' => null,
                'decimal_places' => 0,
                'sort_order' => 5,
                'is_active' => true,
                'options' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('dashboard_card_items')->updateOrInsert(
            [
                'dashboard_card_id' => $buildingCardId,
                'key' => 'assessment_obstacle',
            ],
            [
                'title' => 'ui.damage_dashboard.assessment_blocked',
                'source_bucket' => 'buildingStats',
                'stat_key' => 'unclassified',
                'icon' => 'ki-question-2',
                'link_group' => 'buildings',
                'link_key' => 'assessment_blocked',
                'calculation_type' => 'stat_key',
                'source_model' => null,
                'filter_field' => 'building_damage_status',
                'filter_operator' => 'blank',
                'filter_value' => null,
                'value_suffix' => null,
                'decimal_places' => 0,
                'sort_order' => 6,
                'is_active' => true,
                'options' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('dashboard_card_items')
            ->where('dashboard_card_id', $buildingCardId)
            ->where('key', 'bodies')
            ->update(['sort_order' => 7, 'updated_at' => $now]);

        DB::table('dashboard_card_items')
            ->where('dashboard_card_id', $buildingCardId)
            ->where('key', 'uxo')
            ->update(['sort_order' => 8, 'updated_at' => $now]);

        DB::table('dashboard_card_items')
            ->where('dashboard_card_id', $buildingCardId)
            ->where('key', 'debris')
            ->update(['sort_order' => 9, 'updated_at' => $now]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('dashboard_cards') || ! Schema::hasTable('dashboard_card_items')) {
            return;
        }

        $buildingCardId = DB::table('dashboard_cards')
            ->where('key', 'buildings')
            ->value('id');

        if ($buildingCardId === null) {
            return;
        }

        $now = now();

        DB::table('dashboard_card_items')
            ->where('dashboard_card_id', $buildingCardId)
            ->where('key', 'no_damage')
            ->delete();

        DB::table('dashboard_card_items')->updateOrInsert(
            [
                'dashboard_card_id' => $buildingCardId,
                'key' => 'assessment_obstacle',
            ],
            [
                'title' => 'ui.damage_dashboard.assessment_blocked',
                'source_bucket' => 'buildingStats',
                'stat_key' => 'assessment_obstacle',
                'icon' => 'ki-check-circle',
                'link_group' => 'buildings',
                'link_key' => 'assessment_blocked',
                'calculation_type' => 'stat_key',
                'source_model' => null,
                'filter_field' => 'assessment_obstacle',
                'filter_operator' => '=',
                'filter_value' => 'yes',
                'value_suffix' => null,
                'decimal_places' => 0,
                'sort_order' => 5,
                'is_active' => true,
                'options' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('dashboard_card_items')
            ->where('dashboard_card_id', $buildingCardId)
            ->where('key', 'bodies')
            ->update(['sort_order' => 6, 'updated_at' => $now]);

        DB::table('dashboard_card_items')
            ->where('dashboard_card_id', $buildingCardId)
            ->where('key', 'uxo')
            ->update(['sort_order' => 7, 'updated_at' => $now]);

        DB::table('dashboard_card_items')
            ->where('dashboard_card_id', $buildingCardId)
            ->where('key', 'debris')
            ->update(['sort_order' => 8, 'updated_at' => $now]);

        DB::table('dashboard_card_items')->updateOrInsert(
            [
                'dashboard_card_id' => $buildingCardId,
                'key' => 'completed',
            ],
            [
                'title' => 'ui.damage_dashboard.completed',
                'source_bucket' => 'buildingStats',
                'stat_key' => 'completed',
                'icon' => 'ki-check-circle',
                'link_group' => 'buildings',
                'link_key' => 'completed',
                'calculation_type' => 'stat_key',
                'source_model' => null,
                'filter_field' => 'field_status',
                'filter_operator' => '=',
                'filter_value' => 'COMPLETED',
                'value_suffix' => null,
                'decimal_places' => 0,
                'sort_order' => 9,
                'is_active' => true,
                'options' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
};
