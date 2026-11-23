<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_confirmations')) {
            return;
        }

        Schema::create('payment_confirmations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_sid')->nullable()->index();
            $table->foreignId('enrollment_id')->nullable()->constrained('course_enrollments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_name');
            $table->string('student_email', 191)->index();
            $table->string('course_name')->nullable();
            $table->string('payment_for', 100)->nullable();
            $table->string('bank_name', 100);
            $table->string('account_number', 100)->nullable();
            $table->string('account_holder')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('proof_file')->nullable();
            $table->string('status', 50)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_confirmations');
    }
};