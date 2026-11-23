<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_finance_dashboard');
        DB::statement(<<<'SQL'
            CREATE VIEW v_finance_dashboard AS
            SELECT
                DATE_FORMAT(COALESCE(p.payment_date, p.created_at), '%Y-%m') AS month,
                COALESCE(e.division, 'unknown') AS division,
                COUNT(*) AS payment_count,
                SUM(CASE WHEN p.status = 'verified' THEN p.amount ELSE 0 END) AS verified_amount,
                SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END) AS pending_amount,
                SUM(p.amount) AS total_amount
            FROM payment_confirmations p
            LEFT JOIN course_enrollments e ON e.id = p.enrollment_id
            GROUP BY month, division
            ORDER BY month DESC, division ASC
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_finance_dashboard');
    }
};
