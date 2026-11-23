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
        // Allow both Indonesian (daring, luring) and English (online, offline), plus hybrid
        DB::statement("ALTER TABLE course_schedules MODIFY COLUMN format ENUM('daring', 'luring', 'hybrid', 'online', 'offline') NOT NULL DEFAULT 'daring'");

        // Normalize existing values to Indonesian standard matching course_enrollments
        DB::statement("UPDATE course_schedules SET format = 'daring' WHERE format = 'online'");
        DB::statement("UPDATE course_schedules SET format = 'luring' WHERE format = 'offline'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE course_schedules SET format = 'online' WHERE format = 'daring'");
        DB::statement("UPDATE course_schedules SET format = 'offline' WHERE format = 'luring'");
        DB::statement("ALTER TABLE course_schedules MODIFY COLUMN format ENUM('offline', 'online', 'hybrid') NOT NULL DEFAULT 'offline'");
    }
};
