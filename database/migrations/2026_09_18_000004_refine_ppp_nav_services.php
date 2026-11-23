<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('services')->where('legacy_nid', 279)->update([
            'title' => 'Penerjemah Tidak Tersumpah',
            'menu_order' => 1,
            'is_active' => true,
            'updated_at' => now(),
        ]);

        DB::table('services')->where('legacy_nid', 280)->update([
            'title' => 'Penerjemah Tersumpah',
            'menu_order' => 2,
            'is_active' => true,
            'updated_at' => now(),
        ]);

        DB::table('services')->where('legacy_nid', 281)->update([
            'title' => 'Interpreting',
            'menu_order' => 3,
            'is_active' => true,
            'updated_at' => now(),
        ]);

        DB::table('services')->where('legacy_nid', 282)->update([
            'title' => 'Proofreading',
            'menu_order' => 4,
            'is_active' => true,
            'updated_at' => now(),
        ]);

        DB::table('services')
            ->whereIn('legacy_nid', [278, 283, 284, 287, 289, 290])
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('services')->where('legacy_nid', 279)->update([
            'title' => 'General Text Translation',
            'menu_order' => 2,
            'updated_at' => now(),
        ]);

        DB::table('services')->where('legacy_nid', 280)->update([
            'title' => 'Sworn Translator',
            'menu_order' => 3,
            'updated_at' => now(),
        ]);

        DB::table('services')->where('legacy_nid', 281)->update([
            'menu_order' => 4,
            'updated_at' => now(),
        ]);

        DB::table('services')->where('legacy_nid', 282)->update([
            'menu_order' => 5,
            'updated_at' => now(),
        ]);

        DB::table('services')
            ->whereIn('legacy_nid', [278, 283, 284, 287, 289, 290])
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};