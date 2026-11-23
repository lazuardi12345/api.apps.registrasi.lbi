<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('services')
            ->where('slug', 'program-pelayanan-bahasa-127')
            ->update([
                'title' => 'Program Pelayanan Bahasa (PPB)',
                'summary' => 'Program Pelayanan Bahasa (PPB) LBI FIB UI untuk kebutuhan komunikasi, akademik, dan profesional.',
                'updated_at' => now(),
            ]);

        DB::table('services')
            ->where('slug', 'program-penerjemahan-dan-penjurubahasaan-1283')
            ->update([
                'title' => 'Program Penerjemahan dan Penjurubahasaan (PPP)',
                'summary' => 'Program Penerjemahan dan Penjurubahasaan (PPP) untuk kebutuhan dokumen, akademik, dan bisnis.',
                'updated_at' => now(),
            ]);

        DB::table('services')
            ->whereIn('slug', ['bahasa-asing-lain', 'program-tes-bahasa'])
            ->delete();
    }

    public function down(): void
    {
        // Intentionally left empty; catalog correction is a data-only repair.
    }
};
