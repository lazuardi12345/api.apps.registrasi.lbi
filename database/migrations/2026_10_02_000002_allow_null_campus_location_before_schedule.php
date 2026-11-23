<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE course_enrollments MODIFY COLUMN campus_location ENUM('depok', 'salemba', 'online') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE course_enrollments SET campus_location = 'depok' WHERE campus_location IS NULL");
        DB::statement("ALTER TABLE course_enrollments MODIFY COLUMN campus_location ENUM('depok', 'salemba', 'online') NOT NULL DEFAULT 'depok'");
    }
};