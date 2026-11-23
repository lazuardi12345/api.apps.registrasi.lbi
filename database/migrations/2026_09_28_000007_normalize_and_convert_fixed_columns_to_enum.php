<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Normalisasi nilai dan konversi kolom tetap (lokasi, mode belajar, status pembayaran, format) ke ENUM.
     */
    public function up(): void
    {
        // 1. Course Enrollments - learning_mode ('daring', 'luring', 'hybrid')
        DB::statement("UPDATE course_enrollments SET learning_mode = 'daring' WHERE LOWER(learning_mode) IN ('daring', 'online')");
        DB::statement("UPDATE course_enrollments SET learning_mode = 'luring' WHERE LOWER(learning_mode) IN ('luring', 'offline')");
        DB::statement("UPDATE course_enrollments SET learning_mode = 'hybrid' WHERE LOWER(learning_mode) = 'hybrid'");
        DB::statement("UPDATE course_enrollments SET learning_mode = 'daring' WHERE learning_mode NOT IN ('daring', 'luring', 'hybrid') OR learning_mode IS NULL");
        DB::statement("ALTER TABLE course_enrollments MODIFY COLUMN learning_mode ENUM('daring', 'luring', 'hybrid') NOT NULL DEFAULT 'daring'");

        // 2. Course Enrollments - campus_location ('depok', 'salemba', 'online')
        DB::statement("UPDATE course_enrollments SET campus_location = 'depok' WHERE LOWER(campus_location) = 'depok'");
        DB::statement("UPDATE course_enrollments SET campus_location = 'salemba' WHERE LOWER(campus_location) = 'salemba'");
        DB::statement("UPDATE course_enrollments SET campus_location = 'online' WHERE LOWER(campus_location) IN ('online', '') OR campus_location IS NULL");
        DB::statement("ALTER TABLE course_enrollments MODIFY COLUMN campus_location ENUM('depok', 'salemba', 'online') NOT NULL DEFAULT 'depok'");

        // 3. Payment Confirmations - status ('pending', 'verified', 'rejected')
        DB::statement("UPDATE payment_confirmations SET status = 'verified' WHERE LOWER(status) IN ('verified', 'confirmed')");
        DB::statement("UPDATE payment_confirmations SET status = 'pending' WHERE LOWER(status) = 'pending'");
        DB::statement("UPDATE payment_confirmations SET status = 'rejected' WHERE LOWER(status) = 'rejected'");
        DB::statement("ALTER TABLE payment_confirmations MODIFY COLUMN status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending'");

        // 4. Course Schedules - format ('offline', 'online', 'hybrid')
        DB::statement("UPDATE course_schedules SET format = 'offline' WHERE LOWER(format) IN ('offline', 'luring')");
        DB::statement("UPDATE course_schedules SET format = 'online' WHERE LOWER(format) IN ('online', 'daring')");
        DB::statement("UPDATE course_schedules SET format = 'hybrid' WHERE LOWER(format) = 'hybrid'");
        DB::statement("ALTER TABLE course_schedules MODIFY COLUMN format ENUM('offline', 'online', 'hybrid') NOT NULL DEFAULT 'offline'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE course_enrollments MODIFY COLUMN learning_mode VARCHAR(50) NULL DEFAULT 'daring'");
        DB::statement("ALTER TABLE course_enrollments MODIFY COLUMN campus_location VARCHAR(50) NULL DEFAULT 'depok'");
        DB::statement("ALTER TABLE payment_confirmations MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE course_schedules MODIFY COLUMN format VARCHAR(50) NOT NULL DEFAULT 'offline'");
    }
};
