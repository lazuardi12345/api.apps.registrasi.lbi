<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Master Hari (Schedule Days)
        Schema::create('schedule_days', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Master Waktu / Jam (Schedule Times)
        Schema::create('schedule_times', function (Blueprint $table) {
            $table->id();
            $table->string('time_range', 100)->unique(); // e.g. "08.30 - 13.00 WIB"
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Master Format Pembelajaran (Learning Formats)
        Schema::create('learning_formats', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // daring, luring, hybrid
            $table->string('name', 100);          // Daring (Online), Luring (Tatap Muka), Hybrid
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Master Lokasi Kampus / Kelas (Class Locations)
        Schema::create('class_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // depok, salemba, online
            $table->string('name', 150);          // Kampus UI Depok, Kampus UI Salemba, Online Zoom
            $table->string('building', 150)->nullable(); // Gedung VIII FIB UI, etc.
            $table->text('address')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Master Level Kursus (Course Levels) - Berelasi ke Course Catalogs
        Schema::create('course_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_catalog_id')->constrained('course_catalogs')->cascadeOnDelete();
            $table->string('name', 100);          // e.g. "Arab Tamhidi", "ARB-1", "GE-1"
            $table->string('code', 50)->nullable(); // e.g. "tamhidi", "arb-1"
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['course_catalog_id', 'name']);
        });

        // 6. Relasikan tabel course_schedules ke tabel-tabel master di atas
        Schema::table('course_schedules', function (Blueprint $table) {
            $table->foreignId('course_level_id')->nullable()->after('course_catalog_id')->constrained('course_levels')->nullOnDelete();
            $table->foreignId('schedule_day_id')->nullable()->after('format')->constrained('schedule_days')->nullOnDelete();
            $table->foreignId('schedule_time_id')->nullable()->after('schedule_day_id')->constrained('schedule_times')->nullOnDelete();
            $table->foreignId('learning_format_id')->nullable()->after('schedule_time_id')->constrained('learning_formats')->nullOnDelete();
            $table->foreignId('class_location_id')->nullable()->after('learning_format_id')->constrained('class_locations')->nullOnDelete();
        });

        // ── SEED DATA AWAL ───────────────────────────────────────────

        // Seed Formats
        $now = now();
        DB::table('learning_formats')->insert([
            ['code' => 'daring', 'name' => 'Daring (Online)', 'description' => 'Pembelajaran jarak jauh via Zoom / LMS', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'luring', 'name' => 'Luring (Tatap Muka)', 'description' => 'Pembelajaran tatap muka di kampus', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'hybrid', 'name' => 'Hybrid (Blended)', 'description' => 'Kombinasi tatap muka dan online', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Seed Locations
        DB::table('class_locations')->insert([
            ['code' => 'depok', 'name' => 'Kampus UI Depok', 'building' => 'Gedung VIII FIB UI', 'address' => 'Kampus UI Depok, Jawa Barat 16424', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'salemba', 'name' => 'Kampus UI Salemba', 'building' => 'Gedung IASTH Lt. 3', 'address' => 'Jl. Salemba Raya No. 4, Jakarta Pusat', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'online', 'name' => 'Online via Zoom', 'building' => 'Zoom Cloud Meetings', 'address' => 'Online', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Seed Days
        $days = [
            ['name' => 'Senin & Rabu', 'sort_order' => 1],
            ['name' => 'Selasa & Kamis', 'sort_order' => 2],
            ['name' => 'Senin & Kamis', 'sort_order' => 3],
            ['name' => 'Rabu & Jumat', 'sort_order' => 4],
            ['name' => 'Sabtu', 'sort_order' => 5],
            ['name' => 'Minggu', 'sort_order' => 6],
            ['name' => 'Sabtu & Minggu', 'sort_order' => 7],
        ];
        foreach ($days as $d) {
            DB::table('schedule_days')->insert([
                'name' => $d['name'],
                'sort_order' => $d['sort_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Seed Times
        $times = [
            ['time_range' => '08.00 - 10.00 WIB', 'start' => '08:00:00', 'end' => '10:00:00', 'sort' => 1],
            ['time_range' => '08.30 - 12.30 WIB', 'start' => '08:30:00', 'end' => '12:30:00', 'sort' => 2],
            ['time_range' => '08.30 - 13.00 WIB', 'start' => '08:30:00', 'end' => '13:00:00', 'sort' => 3],
            ['time_range' => '09.00 - 13.00 WIB', 'start' => '09:00:00', 'end' => '13:00:00', 'sort' => 4],
            ['time_range' => '13.00 - 17.00 WIB', 'start' => '13:00:00', 'end' => '17:00:00', 'sort' => 5],
            ['time_range' => '17.00 - 19.00 WIB', 'start' => '17:00:00', 'end' => '19:00:00', 'sort' => 6],
            ['time_range' => '18.00 - 20.00 WIB', 'start' => '18:00:00', 'end' => '20:00:00', 'sort' => 7],
            ['time_range' => '19.00 - 21.00 WIB', 'start' => '19:00:00', 'end' => '21:00:00', 'sort' => 8],
        ];
        foreach ($times as $t) {
            DB::table('schedule_times')->insert([
                'time_range' => $t['time_range'],
                'start_time' => $t['start'],
                'end_time' => $t['end'],
                'sort_order' => $t['sort'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Backfill learning_format_id di course_schedules
        $formatMap = DB::table('learning_formats')->pluck('id', 'code');
        foreach ($formatMap as $code => $fid) {
            DB::table('course_schedules')
                ->where('format', $code)
                ->update(['learning_format_id' => $fid]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_schedules', function (Blueprint $table) {
            $table->dropForeign(['course_level_id']);
            $table->dropForeign(['schedule_day_id']);
            $table->dropForeign(['schedule_time_id']);
            $table->dropForeign(['learning_format_id']);
            $table->dropForeign(['class_location_id']);

            $table->dropColumn([
                'course_level_id',
                'schedule_day_id',
                'schedule_time_id',
                'learning_format_id',
                'class_location_id',
            ]);
        });

        Schema::dropIfExists('course_levels');
        Schema::dropIfExists('class_locations');
        Schema::dropIfExists('learning_formats');
        Schema::dropIfExists('schedule_times');
        Schema::dropIfExists('schedule_days');
    }
};
