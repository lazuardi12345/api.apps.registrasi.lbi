<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_catalogs', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_nid')->nullable()->after('id')->index();
            $table->text('summary')->nullable()->after('title');
            $table->longText('description')->nullable()->after('summary');
            $table->string('category')->nullable()->after('division')->index();
            $table->string('level')->nullable()->after('category');
            $table->text('learning_outcomes')->nullable()->after('level');
            $table->text('target_audience')->nullable()->after('learning_outcomes');
            $table->text('instructor_info')->nullable()->after('target_audience');
            $table->string('duration')->nullable()->after('instructor_info');
            $table->string('registration_url')->nullable()->after('duration');
            $table->string('image_url')->nullable()->after('registration_url');
        });

        Schema::table('course_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('course_catalog_id')->nullable()->after('service_id')->index();
        });

        $catalogs = DB::table('course_catalogs')->get(['id', 'service_id', 'slug', 'title']);
        foreach ($catalogs as $catalog) {
            DB::table('course_schedules')
                ->whereNull('course_catalog_id')
                ->where('service_id', $catalog->service_id)
                ->where(function ($query) use ($catalog) {
                    $query->where('course_slug', $catalog->slug)
                        ->orWhere('program_name', $catalog->title)
                        ->orWhere('program_name', 'like', $catalog->title . '%');
                })
                ->update(['course_catalog_id' => $catalog->id]);
        }
    }

    public function down(): void
    {
        Schema::table('course_schedules', function (Blueprint $table) {
            $table->dropColumn('course_catalog_id');
        });

        Schema::table('course_catalogs', function (Blueprint $table) {
            $table->dropColumn([
                'legacy_nid', 'summary', 'description', 'category', 'level',
                'learning_outcomes', 'target_audience', 'instructor_info',
                'duration', 'registration_url', 'image_url',
            ]);
        });
    }
};
