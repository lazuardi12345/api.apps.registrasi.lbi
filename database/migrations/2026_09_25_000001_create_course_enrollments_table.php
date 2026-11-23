<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_enrollments')) {
            return;
        }

        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_sid')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('division', 50)->nullable()->index();
            $table->string('student_type', 50)->nullable();
            $table->string('term_period', 50)->nullable()->index();
            $table->string('student_name');
            $table->string('student_email', 191)->index();
            $table->string('student_phone', 50)->nullable();
            $table->string('course_name');
            $table->string('level_name')->nullable();
            $table->string('campus_location', 50)->nullable();
            $table->string('learning_mode', 50)->nullable();
            $table->string('schedule_days', 100)->nullable();
            $table->string('schedule_time', 100)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('registration_fee', 14, 2)->nullable();
            $table->text('postpone_reason')->nullable();
            $table->string('status', 50)->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
    }
};