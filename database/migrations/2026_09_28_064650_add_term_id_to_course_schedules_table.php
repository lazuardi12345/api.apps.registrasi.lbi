<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan term_id ke course_schedules agar jadwal mengikuti term.
     */
    public function up(): void
    {
        Schema::table('course_schedules', function (Blueprint $table) {
            // Relasi ke tabel terms (nullable agar data lama tidak rusak)
            $table->foreignId('term_id')
                ->nullable()
                ->after('course_catalog_id')
                ->constrained('terms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('course_schedules', function (Blueprint $table) {
            $table->dropForeign(['term_id']);
            $table->dropColumn('term_id');
        });
    }
};
