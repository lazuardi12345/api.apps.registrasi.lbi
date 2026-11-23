<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('placement_test_tokens')) {
            return;
        }

        Schema::create('placement_test_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_code', 100)->unique();
            $table->string('test_type', 50)->default('DPT'); // Diagnostic Placement Test
            $table->date('activation_start_date');
            $table->date('expiry_date');
            $table->unsignedInteger('licence_length_days')->default(365);
            $table->boolean('is_used')->default(false)->index();

            // Kolom data peserta saat token sudah digunakan
            $table->foreignId('enrollment_id')->nullable()->constrained('course_enrollments')->nullOnDelete();
            $table->string('registration_number', 100)->nullable()->index();
            $table->string('student_name', 255)->nullable();
            $table->string('student_email', 191)->nullable()->index();
            $table->string('student_phone', 50)->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['test_type', 'is_used']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('placement_test_tokens');
    }
};
