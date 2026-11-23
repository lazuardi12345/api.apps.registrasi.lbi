<?php

namespace Database\Seeders;

use App\Models\Term;
use Illuminate\Database\Seeder;

class TermSeeder extends Seeder
{
    public function run(): void
    {
        $terms = [
            // ─── PPB (English & Bahasa Asing) ──────────────────────────────────
            [
                'name'               => 'Term 1 2026',
                'program'            => 'ppb',
                'description'        => 'Periode kursus PPB Term 1 Tahun 2026',
                'academic_year'      => '2026',
                'start_date'         => '2026-01-20',
                'end_date'           => '2026-04-30',
                'registration_open'  => '2025-12-01',
                'registration_close' => '2026-01-17',
                'is_active'          => true,
                'is_current'         => false,
                'sort_order'         => 1,
            ],
            [
                'name'               => 'Term 2 2026',
                'program'            => 'ppb',
                'description'        => 'Periode kursus PPB Term 2 Tahun 2026',
                'academic_year'      => '2026',
                'start_date'         => '2026-05-19',
                'end_date'           => '2026-08-31',
                'registration_open'  => '2026-04-01',
                'registration_close' => '2026-05-16',
                'is_active'          => true,
                'is_current'         => true,
                'sort_order'         => 2,
            ],
            [
                'name'               => 'Term 3 2026',
                'program'            => 'ppb',
                'description'        => 'Periode kursus PPB Term 3 Tahun 2026',
                'academic_year'      => '2026',
                'start_date'         => '2026-09-07',
                'end_date'           => '2026-12-20',
                'registration_open'  => '2026-07-14',
                'registration_close' => '2026-09-05',
                'is_active'          => true,
                'is_current'         => false,
                'sort_order'         => 3,
            ],

            // ─── PPP (Penerjemahan & Penjurubahasaan) ───────────────────────────
            [
                'name'               => 'Term 1 2026',
                'program'            => 'ppp',
                'description'        => 'Periode kursus PPP Term 1 Tahun 2026',
                'academic_year'      => '2026',
                'start_date'         => '2026-02-09',
                'end_date'           => '2026-06-30',
                'registration_open'  => '2025-12-01',
                'registration_close' => '2026-02-06',
                'is_active'          => true,
                'is_current'         => false,
                'sort_order'         => 1,
            ],
            [
                'name'               => 'Term 2 2026',
                'program'            => 'ppp',
                'description'        => 'Periode kursus PPP Term 2 Tahun 2026',
                'academic_year'      => '2026',
                'start_date'         => '2026-07-20',
                'end_date'           => '2026-12-05',
                'registration_open'  => '2026-05-01',
                'registration_close' => '2026-07-17',
                'is_active'          => true,
                'is_current'         => true,
                'sort_order'         => 2,
            ],

            // ─── BIPA ────────────────────────────────────────────────────────────
            [
                'name'               => 'Term 1 2026',
                'program'            => 'bipa',
                'description'        => 'Periode kursus BIPA Term 1 Tahun 2026',
                'academic_year'      => '2026',
                'start_date'         => '2026-01-26',
                'end_date'           => '2026-05-31',
                'registration_open'  => '2025-11-01',
                'registration_close' => '2026-01-23',
                'is_active'          => true,
                'is_current'         => false,
                'sort_order'         => 1,
            ],
            [
                'name'               => 'Term 2 2026',
                'program'            => 'bipa',
                'description'        => 'Periode kursus BIPA Term 2 Tahun 2026',
                'academic_year'      => '2026',
                'start_date'         => '2026-06-15',
                'end_date'           => '2026-10-31',
                'registration_open'  => '2026-04-01',
                'registration_close' => '2026-06-12',
                'is_active'          => true,
                'is_current'         => true,
                'sort_order'         => 2,
            ],
        ];

        foreach ($terms as $data) {
            Term::updateOrCreate(
                ['name' => $data['name'], 'program' => $data['program']],
                $data
            );
        }

        $this->command->info('✅ Default terms seeded for PPB, PPP, and BIPA.');
    }
}
