<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE IF NOT EXISTS password_reset_tokens (
                  email varchar(255) NOT NULL,
                  token varchar(255) NOT NULL,
                  created_at timestamp NULL,
                  PRIMARY KEY (email)
                )
            SQL);

            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
              email varchar(255) NOT NULL,
              token varchar(255) NOT NULL,
              created_at timestamp NULL,
              PRIMARY KEY (email)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
