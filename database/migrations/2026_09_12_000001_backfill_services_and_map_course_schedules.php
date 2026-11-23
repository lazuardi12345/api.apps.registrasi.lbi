<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultServices = [
            [
                'legacy_nid' => 129,
                'title' => 'Program BIPA',
                'slug' => 'program-bipa-129',
                'body' => '<p>Program <strong>BIPA (Bahasa Indonesia untuk Penutur Asing)</strong> dirancang untuk membantu peserta asing memahami bahasa Indonesia secara komunikatif, praktis, dan aplikatif.</p><ul><li>Level A1 hingga C1 berdasarkan CEFR</li><li>Kelas reguler, intensif, dan khusus</li><li>Pengajar berpengalaman serta bersertifikat BIPA</li><li>Sertifikat resmi dari Universitas Indonesia</li></ul>',
                'summary' => 'Program Bahasa Indonesia untuk Penutur Asing (BIPA) dengan kurikulum komunikatif dan sertifikat resmi UI.',
                'icon' => 'bipa',
                'image_url' => null,
                'menu_category' => 'bipa',
                'menu_order' => 1,
                'is_active' => true,
            ],
            [
                'legacy_nid' => 127,
                'title' => 'Program Pelayanan Bahasa (PPB)',
                'slug' => 'program-pelayanan-bahasa-127',
                'body' => '<p><strong>Program Pelayanan Bahasa (PPB)</strong> menyediakan kursus bahasa asing untuk kebutuhan akademik, profesional, dan kebutuhan umum dengan pendekatan modern dan berorientasi hasil.</p><ul><li>Bahasa Inggris untuk komunikasi dan akademik</li><li>Bahasa asing lainnya sesuai kebutuhan peserta</li><li>Kelas online atau offline</li><li>Program korporat dan institusional tersedia</li></ul>',
                'summary' => 'Program Pelayanan Bahasa (PPB) LBI FIB UI untuk kebutuhan komunikasi, akademik, dan profesional.',
                'icon' => 'ppb',
                'image_url' => null,
                'menu_category' => 'ppb_english',
                'menu_order' => 2,
                'is_active' => true,
            ],
            [
                'legacy_nid' => 1283,
                'title' => 'Program Penerjemahan dan Penjurubahasaan (PPP)',
                'slug' => 'program-penerjemahan-dan-penjurubahasaan-1283',
                'body' => '<p>Layanan <strong>Penerjemahan & Penjurubahasaan (PPP)</strong> LBI FIB UI mencakup penerjemahan dokumen resmi, naskah ilmiah, dan materi profesional.</p><ul><li>Penerjemah bersertifikat dan berpengalaman</li><li>Menangani berbagai bidang: akademik, hukum, medis, dan bisnis</li><li>Layanan interpretasi dan proofreading juga tersedia</li><li>Hasil terjamin akurat, rapi, dan sesuai konteks</li></ul>',
                'summary' => 'Program Penerjemahan dan Penjurubahasaan (PPP) untuk kebutuhan dokumen, akademik, dan bisnis.',
                'icon' => 'ppp',
                'image_url' => null,
                'menu_category' => 'ppp',
                'menu_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($defaultServices as $service) {
            DB::table('services')->updateOrInsert(
                ['slug' => $service['slug']],
                array_merge($service, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $serviceIdByDivision = [
            'bipa' => DB::table('services')->where('slug', 'program-bipa-129')->value('id'),
            'ppb_english' => DB::table('services')->where('slug', 'program-pelayanan-bahasa-127')->value('id'),
            'ppp' => DB::table('services')->where('slug', 'program-penerjemahan-dan-penjurubahasaan-1283')->value('id'),
        ];

        foreach ($serviceIdByDivision as $division => $serviceId) {
            if ($serviceId) {
                DB::table('course_schedules')
                    ->where('division', $division)
                    ->whereNull('service_id')
                    ->update(['service_id' => $serviceId]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally left empty; this is a data backfill migration.
    }
};
