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
        if (Schema::hasTable('student_profiles') && !Schema::hasColumn('student_profiles', 'photo_url')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->longText('photo_url')->nullable()->after('address');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('student_profiles') && Schema::hasColumn('student_profiles', 'photo_url')) {
            Schema::table('student_profiles', function (Blueprint $table) {
                $table->dropColumn('photo_url');
            });
        }
    }
};
