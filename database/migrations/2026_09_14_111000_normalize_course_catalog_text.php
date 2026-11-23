<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['program_name', 'batch', 'schedule_text', 'location', 'notes'] as $column) {
            DB::table('course_schedules')
                ->where($column, 'like', '%???%')
                ->update([$column => DB::raw("REPLACE(`{$column}`, '???', ' - ')")]);
        }
    }

    public function down(): void
    {
        // The original replacement characters are not safely recoverable.
    }
};