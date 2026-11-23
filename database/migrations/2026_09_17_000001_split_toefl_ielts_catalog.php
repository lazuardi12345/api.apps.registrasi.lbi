<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $catalogId = DB::table('course_catalogs')
            ->where('slug', 'toefl-ielts-preparation')
            ->value('id');

        if (!$catalogId) {
            return;
        }

        DB::table('course_schedules')
            ->where('course_catalog_id', $catalogId)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        DB::table('course_catalogs')
            ->where('id', $catalogId)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $catalogId = DB::table('course_catalogs')
            ->where('slug', 'toefl-ielts-preparation')
            ->value('id');

        if (!$catalogId) {
            return;
        }

        DB::table('course_schedules')
            ->where('course_catalog_id', $catalogId)
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);

        DB::table('course_catalogs')
            ->where('id', $catalogId)
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};