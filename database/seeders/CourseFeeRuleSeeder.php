<?php

namespace Database\Seeders;

use App\Models\CourseFeeRule;
use Illuminate\Database\Seeder;

class CourseFeeRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rules = [
            // PPB - Tes Penempatan (Placement Test)
            [
                'name' => 'Biaya Tes Penempatan PPB (Umum/Default)',
                'fee_type' => 'placement_test',
                'division' => 'ppb',
                'course_catalog_id' => null,
                'course_level_id' => null,
                'term_id' => null,
                'course_schedule_id' => null,
                'amount' => 160000.00,
                'is_active' => true,
            ],
            // PPB - Registrasi Kursus (Tanpa Tes)
            [
                'name' => 'Biaya Registrasi Kursus PPB (Umum/Default)',
                'fee_type' => 'registration',
                'division' => 'ppb',
                'course_catalog_id' => null,
                'course_level_id' => null,
                'term_id' => null,
                'course_schedule_id' => null,
                'amount' => 160000.00,
                'is_active' => true,
            ],
            // BIPA - Registrasi Kursus
            [
                'name' => 'Biaya Registrasi Program BIPA (Umum/Default)',
                'fee_type' => 'registration',
                'division' => 'bipa',
                'course_catalog_id' => null,
                'course_level_id' => null,
                'term_id' => null,
                'course_schedule_id' => null,
                'amount' => 200000.00,
                'is_active' => true,
            ],
            // PPP - Registrasi / Tes Layanan PPP
            [
                'name' => 'Biaya Registrasi / Tes Layanan PPP (Umum/Default)',
                'fee_type' => 'registration',
                'division' => 'ppp',
                'course_catalog_id' => null,
                'course_level_id' => null,
                'term_id' => null,
                'course_schedule_id' => null,
                'amount' => 250000.00,
                'is_active' => true,
            ],
            [
                'name' => 'Biaya Tes EPT / Kemahiran PPP',
                'fee_type' => 'placement_test',
                'division' => 'ppp',
                'course_catalog_id' => null,
                'course_level_id' => null,
                'term_id' => null,
                'course_schedule_id' => null,
                'amount' => 250000.00,
                'is_active' => true,
            ],
        ];

        foreach ($rules as $rule) {
            CourseFeeRule::firstOrCreate(
                [
                    'fee_type' => $rule['fee_type'],
                    'division' => $rule['division'],
                    'course_catalog_id' => $rule['course_catalog_id'],
                    'term_id' => $rule['term_id'],
                ],
                $rule
            );
        }
    }
}
