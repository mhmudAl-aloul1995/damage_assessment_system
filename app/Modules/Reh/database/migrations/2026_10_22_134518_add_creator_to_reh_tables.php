<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['reh_buildings', 'reh_units'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'creator')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->text('creator')->nullable();
                });
            }

            $connection = Schema::getConnection();
            $connection->table($tableName)->whereNull('creator')->select(['id', 'source'])->orderBy('id')->chunkById(100, function ($records) use ($connection, $tableName): void {
                foreach ($records as $record) {
                    $source = json_decode($record->source, true, flags: JSON_THROW_ON_ERROR);
                    $creator = $source['Creator'] ?? $source['creator'] ?? null;
                    if ($creator !== null) {
                        $connection->table($tableName)->where('id', $record->id)->whereNull('creator')->update(['creator' => $creator]);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['reh_buildings', 'reh_units'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('creator');
            });
        }
    }
};
