<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('services', 'video_url')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->string('video_url', 500)->nullable()->after('image_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('services', 'video_url')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->dropColumn('video_url');
            });
        }
    }
};