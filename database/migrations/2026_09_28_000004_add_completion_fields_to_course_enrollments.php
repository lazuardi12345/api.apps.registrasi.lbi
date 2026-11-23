<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 3A: Tambah kolom business logic ke course_enrollments.
     *
     * - registration_number : Nomor pendaftaran unik (PPB-2026-0001)
     * - completion_status   : Status kelulusan (lulus / tidak_lulus / dalam_proses)
     * - sar_document_path   : Path dokumen SAR/Sertifikat Akademik
     * - certificate_path    : Path sertifikat kelulusan
     */
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            // registration_number & completion_status sudah ada dari migrasi sebelumnya
            // Tambah kolom dokumen baru
            if (! Schema::hasColumn('course_enrollments', 'sar_document_path')) {
                $table->string('sar_document_path')->nullable()->after('metadata')
                      ->comment('Path dokumen SAR / Sertifikat Akademik');
            }
            if (! Schema::hasColumn('course_enrollments', 'certificate_path')) {
                $table->string('certificate_path')->nullable()->after('sar_document_path')
                      ->comment('Path sertifikat kelulusan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropColumnIfExists(['sar_document_path', 'certificate_path']);
        });
    }
};
