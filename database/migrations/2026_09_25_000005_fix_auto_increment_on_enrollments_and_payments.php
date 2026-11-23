<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `course_enrollments` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;');
        DB::statement('ALTER TABLE `payment_confirmations` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `course_enrollments` MODIFY `id` bigint(20) unsigned NOT NULL;');
        DB::statement('ALTER TABLE `payment_confirmations` MODIFY `id` bigint(20) unsigned NOT NULL;');
    }
};
