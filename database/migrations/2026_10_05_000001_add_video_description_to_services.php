<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('services', 'video_description')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->longText('video_description')->nullable()->after('summary');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('services', 'video_description')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->dropColumn('video_description');
            });
        }
    }
};
