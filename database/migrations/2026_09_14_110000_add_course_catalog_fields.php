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
        Schema::table('course_schedules', function (Blueprint $table) {
            $table->string('course_slug')->nullable()->unique()->after('program_name');
            $table->text('course_summary')->nullable()->after('level');
        });

        DB::table('course_schedules')->orderBy('id')->each(function ($course) {
            $baseSlug = Str::slug($course->program_name ?: 'course-' . $course->id);
            $slug = $baseSlug;
            $suffix = 2;

            while (DB::table('course_schedules')->where('course_slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $suffix++;
            }

            DB::table('course_schedules')->where('id', $course->id)->update([
                'course_slug' => $slug,
                'course_summary' => $course->notes,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('course_schedules', function (Blueprint $table) {
            $table->dropUnique(['course_slug']);
            $table->dropColumn(['course_slug', 'course_summary']);
        });
    }
};