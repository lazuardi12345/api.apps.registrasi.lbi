<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fase 5 - Indexing & Performa Database.
     */
    public function up(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->index(['division', 'status', 'term_period'], 'idx_div_status_term');
            $table->index(['student_email', 'division'], 'idx_email_div');
            $table->index(['registration_number'], 'idx_reg_number');
            $table->index(['completion_status'], 'idx_completion_status');
        });

        // Index tambahan untuk payment_confirmations bila belum ada
        Schema::table('payment_confirmations', function (Blueprint $table) {
            $table->index(['enrollment_id', 'status'], 'idx_pay_enrollment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropIndex('idx_div_status_term');
            $table->dropIndex('idx_email_div');
            $table->dropIndex('idx_reg_number');
            $table->dropIndex('idx_completion_status');
        });

        Schema::table('payment_confirmations', function (Blueprint $table) {
            $table->dropIndex('idx_pay_enrollment_status');
        });
    }
};
