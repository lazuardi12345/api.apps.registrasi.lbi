<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan term_id ke tabel course_enrollments agar hasil isian webform
     * terikat langsung dengan master term akademik yang sedang aktif / dipilih.
     */
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->foreignId('term_id')
                ->nullable()
                ->after('course_schedule_id')
                ->constrained('terms')
                ->nullOnDelete();

            $table->index(['division', 'term_id'], 'idx_enrollments_division_term');
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropForeign(['term_id']);
            $table->dropIndex('idx_enrollments_division_term');
            $table->dropColumn('term_id');
        });
    }
};
