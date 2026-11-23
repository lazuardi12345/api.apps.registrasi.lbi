<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $orphanServices = DB::table('course_schedules')
            ->whereNotNull('service_id')
            ->whereNotIn('service_id', DB::table('services')->select('id'))
            ->count();
        $orphanCatalogs = DB::table('course_schedules')
            ->whereNotNull('course_catalog_id')
            ->whereNotIn('course_catalog_id', DB::table('course_catalogs')->select('id'))
            ->count();

        if ($orphanServices || $orphanCatalogs) {
            throw new \RuntimeException(sprintf(
                'Cannot harden course relationships: %d orphan service links and %d orphan catalog links found.',
                $orphanServices,
                $orphanCatalogs
            ));
        }

        Schema::table('course_schedules', function (Blueprint $table): void {
            $table->foreign('service_id', 'course_schedules_service_id_foreign')
                ->references('id')->on('services')->nullOnDelete();
            $table->foreign('course_catalog_id', 'course_schedules_catalog_id_foreign')
                ->references('id')->on('course_catalogs')->nullOnDelete();
            $table->index(['service_id', 'is_active'], 'course_schedules_service_active_index');
            $table->index(['course_catalog_id', 'is_active'], 'course_schedules_catalog_active_index');
            $table->index(['division', 'is_active', 'is_open'], 'course_schedules_division_open_index');
        });
    }

    public function down(): void
    {
        Schema::table('course_schedules', function (Blueprint $table): void {
            $table->dropIndex('course_schedules_service_active_index');
            $table->dropIndex('course_schedules_catalog_active_index');
            $table->dropIndex('course_schedules_division_open_index');
            $table->dropForeign('course_schedules_service_id_foreign');
            $table->dropForeign('course_schedules_catalog_id_foreign');
        });
    }
};