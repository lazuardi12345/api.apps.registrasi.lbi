<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->foreignId('course_catalog_id')->nullable()->after('user_id')->constrained('course_catalogs')->nullOnDelete();
            $table->foreignId('course_schedule_id')->nullable()->after('course_catalog_id')->constrained('course_schedules')->nullOnDelete();
            $table->string('webform_id', 128)->nullable()->after('legacy_sid')->index();
            $table->unsignedBigInteger('legacy_uid')->nullable()->after('webform_id')->index();
            $table->string('registration_source', 50)->nullable()->after('legacy_uid')->index();
            $table->string('payment_status', 50)->nullable()->after('status')->index();
            $table->timestamp('submitted_at')->nullable()->after('payment_status')->index();
            $table->json('metadata')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropForeign(['course_catalog_id']);
            $table->dropForeign(['course_schedule_id']);
            $table->dropColumn([
                'course_catalog_id',
                'course_schedule_id',
                'webform_id',
                'legacy_uid',
                'registration_source',
                'payment_status',
                'submitted_at',
                'metadata',
            ]);
        });
    }
};