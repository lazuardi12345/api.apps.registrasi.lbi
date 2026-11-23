<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel master term/periode kursus per program LBI FIB UI.
     * Setiap term mewakili satu periode akademik (Term 1 2026, Term 2 2026, dll)
     * yang berlaku per program (ppb, ppp, bipa).
     */
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();

            // Nama Term, contoh: "Term 1 2026", "Term 2 2026", "Term Ganjil 2026/2027"
            $table->string('name', 100);

            // Program: ppb | ppp | bipa | all (berlaku semua program)
            $table->string('program', 20)->default('all')->index();

            // Deskripsi singkat opsional
            $table->string('description', 255)->nullable();

            // Tahun akademik, contoh: "2026", "2026/2027"
            $table->string('academic_year', 20)->nullable();

            // Tanggal kursus berjalan
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Window pendaftaran
            $table->date('registration_open')->nullable();
            $table->date('registration_close')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_current')->default(false); // term yang sedang berjalan

            // Urutan tampil
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['program', 'is_active']);
            $table->index(['is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
