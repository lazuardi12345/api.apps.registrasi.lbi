<?php

namespace App\Actions\Admin\Dashboard;

use Illuminate\Support\Facades\DB;

class GetEnrollmentAnalyticsAction
{
    /**
     * Fase 4C - Analytics dashboard admin.
     *
     * Mengembalikan:
     * - Total per division
     * - Total per status
     * - Total per term_period (tren pendaftaran)
     * - Revenue summary per division
     * - Top 10 kursus terpopuler
     * - Pendaftaran 30 hari terakhir (daily)
     */
    public function execute(string|array|null $division = null): array
    {
        $applyDivision = function ($query, string $column = 'division') use ($division) {
            if (is_array($division)) {
                return $query->whereIn($column, $division);
            }
            if (is_string($division) && $division !== '' && $division !== 'all') {
                return $query->where($column, $division);
            }
            return $query;
        };

        // --- Per Division ---
        $perDivisionQuery = DB::table('course_enrollments')
            ->select('division', DB::raw('count(*) as total'));
        $perDivision = $applyDivision($perDivisionQuery, 'division')
            ->groupBy('division')
            ->orderBy('division')
            ->get();

        // --- Per Status ---
        $perStatusQuery = DB::table('course_enrollments')
            ->select('status', DB::raw('count(*) as total'));
        $perStatus = $applyDivision($perStatusQuery, 'division')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        // --- Per Term Period ---
        $perPeriodQuery = DB::table('course_enrollments')
            ->select('term_period', DB::raw('count(*) as total'))
            ->whereNotNull('term_period');
        $perPeriod = $applyDivision($perPeriodQuery, 'division')
            ->groupBy('term_period')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        // --- Revenue per Division ---
        $revenueQuery = DB::table('payment_confirmations as pc')
            ->leftJoin('course_enrollments as ce', 'pc.enrollment_id', '=', 'ce.id')
            ->select(
                'ce.division',
                DB::raw("SUM(CASE WHEN pc.status='verified' THEN pc.amount ELSE 0 END) as confirmed_revenue"),
                DB::raw("SUM(CASE WHEN pc.status='pending' THEN pc.amount ELSE 0 END) as pending_revenue"),
                DB::raw("COUNT(CASE WHEN pc.status='verified' THEN 1 END) as verified_count"),
                DB::raw("COUNT(CASE WHEN pc.status='pending' THEN 1 END) as pending_count")
            );
        $revenueSummary = $applyDivision($revenueQuery, 'ce.division')
            ->groupBy('ce.division')
            ->orderBy('ce.division')
            ->get();

        // --- Top 10 Kursus Terpopuler (Konsolidasi format Tatap Muka & Online) ---
        $cleanCourseExpr = "TRIM(REGEXP_REPLACE(course_name, '\\\\s*\\\\((Tatap Muka|Online|Daring|Luring|Kelas Daring|Offline)\\\\)', ''))";

        $topCoursesQuery = DB::table('course_enrollments')
            ->select(
                DB::raw("{$cleanCourseExpr} as course_name"),
                DB::raw('count(*) as total')
            )
            ->whereNotNull('course_name')
            ->where('course_name', '!=', '');
        $topCourses = $applyDivision($topCoursesQuery, 'division')
            ->groupBy(DB::raw($cleanCourseExpr))
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // --- Pendaftaran 30 Hari Terakhir (Daily) ---
        $dailyTrendQuery = DB::table('course_enrollments')
            ->select(
                DB::raw('DATE(submitted_at) as date'),
                DB::raw('count(*) as total')
            )
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', now()->subDays(30));
        $dailyTrend = $applyDivision($dailyTrendQuery, 'division')
            ->groupBy(DB::raw('DATE(submitted_at)'))
            ->orderBy('date')
            ->get();

        return [
            'per_division'   => $perDivision,
            'per_status'     => $perStatus,
            'per_period'     => $perPeriod,
            'revenue_summary'=> $revenueSummary,
            'top_courses'    => $topCourses,
            'daily_trend'    => $dailyTrend,
        ];
    }
}
