<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE course_enrollments MODIFY COLUMN learning_mode ENUM('daring', 'luring', 'hybrid') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE course_enrollments SET learning_mode = 'daring' WHERE learning_mode IS NULL");
        DB::statement("ALTER TABLE course_enrollments MODIFY COLUMN learning_mode ENUM('daring', 'luring', 'hybrid') NOT NULL DEFAULT 'daring'");
    }
};