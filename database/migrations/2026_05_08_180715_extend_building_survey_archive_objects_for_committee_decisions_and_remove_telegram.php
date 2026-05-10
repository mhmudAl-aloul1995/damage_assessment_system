<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('building_survey_archive_objects', function (Blueprint $table): void {
            if (Schema::hasColumn('building_survey_archive_objects', 'return_request_id') && Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['return_request_id']);
            }
        });

        Schema::table('building_survey_archive_objects', function (Blueprint $table): void {
            if (Schema::hasColumn('building_survey_archive_objects', 'return_request_id')) {
                $table->foreignId('return_request_id')->nullable()->change();
                $table->foreign('return_request_id')
                    ->references('id')
                    ->on('building_survey_return_requests')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('building_survey_archive_objects', 'source_type')) {
                $table->string('source_type', 50)->default('return_request')->after('building_globalid')->index();
            }

            if (! Schema::hasColumn('building_survey_archive_objects', 'committee_decision_id')) {
                $table->foreignId('committee_decision_id')
                    ->nullable()
                    ->after('return_request_id')
                    ->constrained('committee_decisions')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('building_survey_archive_objects', 'housing_unit_objectid')) {
                $table->unsignedBigInteger('housing_unit_objectid')->nullable()->after('building_globalid')->index();
            }

            if (! Schema::hasColumn('building_survey_archive_objects', 'housing_unit_globalid')) {
                $table->string('housing_unit_globalid')->nullable()->after('housing_unit_objectid')->index();
            }
        });

        Schema::table('committee_decisions', function (Blueprint $table): void {
            foreach (['telegram_status', 'telegram_sent_at', 'telegram_last_attempt_at', 'telegram_last_error'] as $column) {
                if (Schema::hasColumn('committee_decisions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->dropIndex(['telegram_chat_id']);
                $table->dropColumn('telegram_chat_id');
            }
        });

        Schema::dropIfExists('telegram_broadcasts');
        Schema::dropIfExists('telegram_discovered_chats');
        Schema::dropIfExists('telegram_destination_link_sessions');
        Schema::dropIfExists('telegram_destination_preferences');
        Schema::dropIfExists('telegram_destinations');
        Schema::dropIfExists('telegram_settings');
        Schema::dropIfExists('telegram_link_sessions');
        Schema::dropIfExists('telegram_integrations');
    }

    public function down(): void
    {
        Schema::create('telegram_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('type', 20);
            $table->string('status', 30)->default('pending');
            $table->string('telegram_chat_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_title')->nullable();
            $table->string('linked_by')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['telegram_chat_id', 'type']);
        });

        Schema::create('telegram_link_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telegram_integration_id')->constrained('telegram_integrations')->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('status', 30)->default('pending');
            $table->json('telegram_payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['telegram_integration_id', 'status']);
        });

        Schema::create('telegram_settings', function (Blueprint $table): void {
            $table->id();
            $table->text('bot_token')->nullable();
            $table->string('bot_username')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('parse_mode', 20)->default('HTML');
            $table->timestamps();
        });

        Schema::create('telegram_destinations', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('scope_type', 50)->default('system');
            $table->string('name');
            $table->string('status', 30)->default('pending');
            $table->string('chat_id')->nullable();
            $table->string('telegram_user_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->string('telegram_last_name')->nullable();
            $table->string('telegram_link_token')->nullable()->unique();
            $table->string('related_model_type')->nullable();
            $table->unsignedBigInteger('related_model_id')->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->json('extra_settings')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['scope_type', 'context_id']);
            $table->index(['related_model_type', 'related_model_id']);
            $table->index(['chat_id', 'is_active']);
        });

        Schema::create('telegram_destination_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telegram_destination_id')->constrained('telegram_destinations')->cascadeOnDelete();
            $table->boolean('notify_new_records')->default(false);
            $table->boolean('notify_errors')->default(false);
            $table->boolean('notify_status_changes')->default(true);
            $table->boolean('notify_reports')->default(false);
            $table->boolean('notify_broadcasts')->default(false);
            $table->timestamps();

            $table->unique('telegram_destination_id');
        });

        Schema::create('telegram_destination_link_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telegram_destination_id');
            $table->string('token')->unique();
            $table->string('status', 30)->default('pending');
            $table->json('telegram_payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->foreign('telegram_destination_id', 'tdls_tg_dest_id_fk')
                ->references('id')
                ->on('telegram_destinations')
                ->onDelete('cascade');
        });

        Schema::create('telegram_discovered_chats', function (Blueprint $table): void {
            $table->id();
            $table->string('chat_id')->unique();
            $table->string('chat_type', 30);
            $table->string('title')->nullable();
            $table->string('username')->nullable();
            $table->text('last_message_text')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->foreignId('telegram_destination_id')->nullable()->constrained('telegram_destinations')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('telegram_broadcasts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->longText('message');
            $table->string('target_type', 30)->default('all');
            $table->string('scope_type', 50)->nullable();
            $table->json('destination_ids_json')->nullable();
            $table->json('user_ids_json')->nullable();
            $table->json('context_ids_json')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status', 30)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->string('telegram_chat_id')->nullable()->after('username_arcgis');
                $table->index('telegram_chat_id');
            }
        });

        Schema::table('committee_decisions', function (Blueprint $table): void {
            if (! Schema::hasColumn('committee_decisions', 'telegram_status')) {
                $table->string('telegram_status')->nullable()->after('completed_at');
                $table->timestamp('telegram_sent_at')->nullable()->after('telegram_status');
                $table->timestamp('telegram_last_attempt_at')->nullable()->after('telegram_sent_at');
                $table->text('telegram_last_error')->nullable()->after('telegram_last_attempt_at');
            }
        });

        Schema::table('building_survey_archive_objects', function (Blueprint $table): void {
            if (Schema::hasColumn('building_survey_archive_objects', 'committee_decision_id')) {
                if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                    $table->dropForeign(['committee_decision_id']);
                }

                $table->dropColumn('committee_decision_id');
            }

            foreach (['source_type', 'housing_unit_objectid', 'housing_unit_globalid'] as $column) {
                if (Schema::hasColumn('building_survey_archive_objects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('building_survey_archive_objects', function (Blueprint $table): void {
            if (Schema::hasColumn('building_survey_archive_objects', 'return_request_id')) {
                if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                    $table->dropForeign(['return_request_id']);
                }

                $table->foreignId('return_request_id')->nullable(false)->change();
                $table->foreign('return_request_id')
                    ->references('id')
                    ->on('building_survey_return_requests')
                    ->cascadeOnDelete();
            }
        });
    }
};
