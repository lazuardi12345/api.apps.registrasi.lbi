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
        Schema::create('course_schedules', function (Blueprint $table) {
            $table->id();
            // Foreign key to services/programs table
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->string('program_name');          // e.g. "BIPA A1", "PPB Inggris Intensif"
            $table->string('division')->default('ppb_english'); // bipa | ppb_english | ppp
            $table->string('batch')->nullable();     // e.g. "Batch 3 - 2025"
            $table->string('level')->nullable();     // e.g. "Beginner", "A1-A2"
            $table->string('format')->default('offline'); // offline | online | hybrid
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('schedule_text')->nullable(); // e.g. "Senin & Rabu, 08.00-10.00 WIB"
            $table->unsignedInteger('capacity')->default(20);
            $table->unsignedInteger('registered')->default(0);
            $table->decimal('fee', 12, 2)->default(0);
            $table->string('fee_display')->nullable();   // e.g. "Hubungi Kami"
            $table->date('registration_open')->nullable();
            $table->date('registration_close')->nullable();
            $table->string('location')->nullable();      // e.g. "Gedung Soedijono Lt. 2"
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_open')->default(true);   // open for registration
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_schedules');
    }
};
