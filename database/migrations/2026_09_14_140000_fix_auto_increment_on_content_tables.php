<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('partners')) {
            DB::statement('ALTER TABLE partners MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        if (Schema::hasTable('landing_sliders')) {
            DB::statement('ALTER TABLE landing_sliders MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        if (Schema::hasTable('landing_banners')) {
            DB::statement('ALTER TABLE landing_banners MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op or keep as is
    }
};
