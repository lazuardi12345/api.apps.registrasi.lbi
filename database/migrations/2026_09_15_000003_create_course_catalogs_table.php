<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('division')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $ppbServiceId = DB::table('services')
            ->where('slug', 'program-pelayanan-bahasa-127')
            ->value('id');

        $schedules = DB::table('course_schedules')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['service_id', 'program_name', 'course_slug', 'division', 'sort_order']);

        $seen = [];
        foreach ($schedules as $schedule) {
            $serviceId = $schedule->service_id ?: ($ppbServiceId && in_array($schedule->division, ['ppb', 'ppb_english', 'ppb_foreign', 'testing'], true) ? $ppbServiceId : null);
            if (!$serviceId) {
                continue;
            }

            $title = preg_replace('/\s*\((?:daring|tatap muka|luring|daring\/luring|online|offline)\)\s*/i', '', $schedule->program_name);
            $title = preg_replace('/\s+(?:senin|selasa|rabu|kamis|jumat|sabtu|minggu).*$/i', '', trim((string) $title));
            $title = trim((string) $title);
            $slug = Str::slug($title);

            if ($title === '' || $slug === '' || isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;
            DB::table('course_catalogs')->insert([
                'service_id' => $serviceId,
                'title' => $title,
                'slug' => $slug,
                'division' => $schedule->division === 'ppb' ? 'ppb_foreign' : $schedule->division,
                'is_active' => true,
                'sort_order' => $schedule->sort_order ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_catalogs');
    }
};
