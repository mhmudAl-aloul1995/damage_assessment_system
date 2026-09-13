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

        $now = now();

        $this->updateCommitteeItem('buildings', 'ui.damage_dashboard.current_committee_review', 3);
        $this->updateCommitteeItem('housing', 'ui.damage_dashboard.current_committee_review', 3);

        $this->upsertArchivedCommitteeItem('buildings', 'buildingStats', 4, $now);
        $this->upsertArchivedCommitteeItem('housing', 'unitStats', 4, $now);

        $this->shiftSortOrders('buildings', 4);
        $this->shiftSortOrders('housing', 4);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('dashboard_cards') || ! Schema::hasTable('dashboard_card_items')) {
            return;
        }

        DB::table('dashboard_card_items')
            ->whereIn('dashboard_card_id', DB::table('dashboard_cards')->whereIn('key', ['buildings', 'housing'])->select('id'))
            ->where('key', 'archived_committee_review')
            ->delete();

        $this->unshiftSortOrders('buildings', 4);
        $this->unshiftSortOrders('housing', 4);

        $this->updateCommitteeItem('buildings', 'ui.damage_dashboard.committee_review', 3);
        $this->updateCommitteeItem('housing', 'ui.damage_dashboard.committee_review', 3);
    }

    private function updateCommitteeItem(string $cardKey, string $title, int $sortOrder): void
    {
        $cardId = DB::table('dashboard_cards')->where('key', $cardKey)->value('id');

        if ($cardId === null) {
            return;
        }

        DB::table('dashboard_card_items')
            ->where('dashboard_card_id', $cardId)
            ->where('key', 'committee_review')
            ->update([
                'title' => $title,
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ]);
    }

    private function upsertArchivedCommitteeItem(string $cardKey, string $sourceBucket, int $sortOrder, mixed $now): void
    {
        $cardId = DB::table('dashboard_cards')->where('key', $cardKey)->value('id');

        if ($cardId === null) {
            return;
        }

        DB::table('dashboard_card_items')->updateOrInsert(
            [
                'dashboard_card_id' => $cardId,
                'key' => 'archived_committee_review',
            ],
            [
                'title' => 'ui.damage_dashboard.archived_committee_review',
                'source_bucket' => $sourceBucket,
                'stat_key' => 'archived_committee_review',
                'icon' => 'ki-archive',
                'link_group' => $cardKey === 'buildings' ? 'buildings' : 'housing',
                'link_key' => 'archived_committee_review',
                'calculation_type' => 'stat_key',
                'source_model' => null,
                'filter_field' => null,
                'filter_operator' => null,
                'filter_value' => null,
                'value_suffix' => null,
                'decimal_places' => 0,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'options' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function shiftSortOrders(string $cardKey, int $fromSortOrder): void
    {
        $cardId = DB::table('dashboard_cards')->where('key', $cardKey)->value('id');

        if ($cardId === null) {
            return;
        }

        DB::table('dashboard_card_items')
            ->where('dashboard_card_id', $cardId)
            ->where('key', '!=', 'archived_committee_review')
            ->where('sort_order', '>=', $fromSortOrder)
            ->increment('sort_order');
    }

    private function unshiftSortOrders(string $cardKey, int $fromSortOrder): void
    {
        $cardId = DB::table('dashboard_cards')->where('key', $cardKey)->value('id');

        if ($cardId === null) {
            return;
        }

        DB::table('dashboard_card_items')
            ->where('dashboard_card_id', $cardId)
            ->where('sort_order', '>', $fromSortOrder)
            ->decrement('sort_order');
    }
};
