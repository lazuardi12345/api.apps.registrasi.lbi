<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_division_summary');
        DB::statement(<<<'SQL'
            CREATE VIEW v_division_summary AS
            SELECT
                division,
                COUNT(*) AS total_enrollments,
                SUM(CASE WHEN status IN ('pending', 'pending_payment') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status IN ('paid', 'verified') THEN 1 ELSE 0 END) AS paid,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
            FROM course_enrollments
            GROUP BY division
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_division_summary');
    }
};
