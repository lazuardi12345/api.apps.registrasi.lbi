<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('services', 'menu_category')) {
            Schema::table('services', function (Blueprint $table) {
                $table->string('menu_category', 50)->nullable()->after('icon')->index();
            });
        }

        if (!Schema::hasColumn('services', 'menu_order')) {
            Schema::table('services', function (Blueprint $table) {
                $table->unsignedInteger('menu_order')->default(0)->after('menu_category');
            });
        }

        $this->backfillMenuCategories();
    }

    public function down(): void
    {
        if (Schema::hasColumn('services', 'menu_category')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropIndex(['menu_category']);
            });
        }

        if (Schema::hasColumn('services', 'menu_category') || Schema::hasColumn('services', 'menu_order')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn(['menu_category', 'menu_order']);
            });
        }
    }

    private function backfillMenuCategories(): void
    {
        $rules = [
            'bipa' => ['bipa', 'binon', 'kosakata'],
            'ppp' => ['penerjemah', 'interpreting', 'proofreading', 'penjurubahasaan', 'translation', 'penyuntingan'],
            'testing' => ['toefl', 'ielts', 'ept', 'tes ', 'ibt'],
            'ppb_english' => ['english', 'presentation', 'academic', 'conversation', 'writing', 'grammar'],
        ];

        DB::table('services')->select(['id', 'title'])->orderBy('id')->each(function ($service) use ($rules) {
            $title = strtolower($service->title ?? '');
            $category = 'ppb_foreign';

            foreach ($rules as $candidate => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($title, $keyword)) {
                        $category = $candidate;
                        break 2;
                    }
                }
            }

            DB::table('services')->where('id', $service->id)->update([
                'menu_category' => $category,
                'menu_order' => 0,
            ]);
        });
    }
};