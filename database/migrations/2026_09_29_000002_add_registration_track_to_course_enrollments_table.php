<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom registration_track ke tabel course_enrollments
     * untuk memisahkan jalur pendaftaran PPB:
     * - 'placement_test' (Jalur Tes Penempatan)
     * - 'no_test' (Jalur Tanpa Tes: Dasar 1 / Siswa Lanjutan)
     */
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->string('registration_track', 50)->default('no_test')->after('student_type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropIndex(['registration_track']);
            $table->dropColumn('registration_track');
        });
    }
};
