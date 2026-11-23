<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mengubah kolom role di tabel users dan role_name di user_roles menjadi ENUM.
     */
    public function up(): void
    {
        // 1. Pastikan tidak ada nilai null / kosong
        DB::statement("UPDATE users SET role = 'student' WHERE role IS NULL OR role = ''");

        // 2. Ubah kolom users.role ke ENUM
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'administrator',
            'admin',
            'admin_ppb',
            'admin_bipa',
            'admin_ppp',
            'finance',
            'marketing',
            'content_writer',
            'reviewer',
            'teacher',
            'student'
        ) NOT NULL DEFAULT 'student'");

        // 3. Ubah kolom user_roles.role_name ke ENUM
        DB::statement("ALTER TABLE user_roles MODIFY COLUMN role_name ENUM(
            'administrator',
            'admin',
            'admin_ppb',
            'admin_bipa',
            'admin_ppp',
            'finance',
            'marketing',
            'content_writer',
            'reviewer',
            'teacher',
            'student'
        ) NOT NULL DEFAULT 'student'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(255) NULL DEFAULT 'student'");
        DB::statement("ALTER TABLE user_roles MODIFY COLUMN role_name VARCHAR(255) NOT NULL");
    }
};
