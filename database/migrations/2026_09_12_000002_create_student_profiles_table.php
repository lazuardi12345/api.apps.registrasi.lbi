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
        if (!Schema::hasTable('student_profiles')) {
            Schema::create('student_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('legacy_profile_id')->nullable()->unique();
                $table->string('full_name');
                $table->string('identity_number')->nullable();
                $table->string('gender')->nullable();
                $table->string('birth_place')->nullable();
                $table->date('birth_date')->nullable();
                $table->string('phone')->nullable();
                $table->string('education')->nullable();
                $table->string('occupation')->nullable();
                $table->text('address')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
