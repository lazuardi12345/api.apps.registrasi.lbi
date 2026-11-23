<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fase 3C: Backfill registration_number untuk semua enrollment lama
     * yang belum memiliki nomor pendaftaran.
     *
     * Format: {PREFIX}-{TAHUN}-{id 5-digit}
     * Contoh: PPB-2026-00001
     */
    public function up(): void
    {
        $divisionMap = [
            'ppb'         => 'PPB',
            'ppb_foreign' => 'PPBF',
            'ppp'         => 'PPP',
            'bipa'        => 'BIPA',
        ];

        // Ambil semua enrollment yang belum punya registration_number
        DB::table('course_enrollments')
            ->whereNull('registration_number')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($divisionMap) {
                foreach ($rows as $row) {
                    $prefix = $divisionMap[strtolower($row->division ?? '')] ?? 'REG';
                    // Gunakan tahun submitted_at jika ada, fallback ke created_at lalu ke tahun saat ini
                    $year = $row->submitted_at
                        ? (int) substr($row->submitted_at, 0, 4)
                        : ($row->created_at
                            ? (int) substr($row->created_at, 0, 4)
                            : date('Y'));
                    $number = str_pad($row->id, 5, '0', STR_PAD_LEFT);

                    DB::table('course_enrollments')
                        ->where('id', $row->id)
                        ->update(['registration_number' => "{$prefix}-{$year}-{$number}"]);
                }
            });
    }

    public function down(): void
    {
        // Tidak perlu rollback — data yang sudah ada tidak dihapus
    }
};
