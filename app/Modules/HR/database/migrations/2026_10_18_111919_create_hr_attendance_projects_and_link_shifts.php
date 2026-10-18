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
        Schema::create('hr_attendance_projects', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table('hr_shift_assignments', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->constrained('hr_attendance_projects')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_shift_assignments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::dropIfExists('hr_attendance_projects');
    }
};
