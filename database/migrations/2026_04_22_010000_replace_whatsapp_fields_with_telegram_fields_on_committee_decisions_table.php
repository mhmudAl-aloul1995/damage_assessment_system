<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('committee_decisions', 'whatsapp_status')) {
            return;
        }

        Schema::table('committee_decisions', function (Blueprint $table): void {
            $table->renameColumn('whatsapp_status', 'telegram_status');
            $table->renameColumn('whatsapp_sent_at', 'telegram_sent_at');
            $table->renameColumn('whatsapp_last_attempt_at', 'telegram_last_attempt_at');
            $table->renameColumn('whatsapp_last_error', 'telegram_last_error');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('committee_decisions', 'telegram_status')) {
            return;
        }

        Schema::table('committee_decisions', function (Blueprint $table): void {
            $table->renameColumn('telegram_status', 'whatsapp_status');
            $table->renameColumn('telegram_sent_at', 'whatsapp_sent_at');
            $table->renameColumn('telegram_last_attempt_at', 'whatsapp_last_attempt_at');
            $table->renameColumn('telegram_last_error', 'whatsapp_last_error');
        });
    }
};
