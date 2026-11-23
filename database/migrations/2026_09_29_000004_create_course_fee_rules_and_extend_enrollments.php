<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_fee_rules')) {
            Schema::create('course_fee_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name', 191);
                $table->string('fee_type', 30)->index();
                $table->string('division', 50)->nullable()->index();
                $table->foreignId('course_catalog_id')->nullable()->constrained('course_catalogs')->cascadeOnDelete();
                $table->foreignId('course_level_id')->nullable()->constrained('course_levels')->cascadeOnDelete();
                $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
                $table->foreignId('course_schedule_id')->nullable()->constrained('course_schedules')->nullOnDelete();
                $table->decimal('amount', 14, 2)->default(0);
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['fee_type', 'division', 'term_id', 'is_active'], 'idx_course_fee_lookup');
            });
        }

        Schema::table('course_enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('course_enrollments', 'course_level_id')) {
                $table->foreignId('course_level_id')->nullable()->after('course_catalog_id')
                    ->constrained('course_levels')->nullOnDelete();
            }
            if (! Schema::hasColumn('course_enrollments', 'tuition_fee')) {
                $table->decimal('tuition_fee', 14, 2)->nullable()->after('registration_fee');
            }
            if (! Schema::hasColumn('course_enrollments', 'course_fee_rule_id')) {
                $table->foreignId('course_fee_rule_id')->nullable()->after('tuition_fee')
                    ->constrained('course_fee_rules')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('course_enrollments', 'course_fee_rule_id')) {
                $table->dropForeign(['course_fee_rule_id']);
                $table->dropColumn('course_fee_rule_id');
            }
            if (Schema::hasColumn('course_enrollments', 'tuition_fee')) {
                $table->dropColumn('tuition_fee');
            }
            if (Schema::hasColumn('course_enrollments', 'course_level_id')) {
                $table->dropForeign(['course_level_id']);
                $table->dropColumn('course_level_id');
            }
        });

        Schema::dropIfExists('course_fee_rules');
    }
};