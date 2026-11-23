<?php

namespace App\Actions\Admin\Dashboard;

use Illuminate\Support\Facades\DB;

class GetAdminAnalyticsAction
{
    /**
     * Consolidate metrics across students, enrollments, payments, and system activity.
     */
    public function execute(string|array|null $allowedDivisions = null): array
    {
        $applyDivision = function ($query, string $column = 'division') use ($allowedDivisions) {
            if (is_array($allowedDivisions)) {
                return $query->whereIn($column, $allowedDivisions);
            }
            if (is_string($allowedDivisions) && $allowedDivisions !== '' && $allowedDivisions !== 'all') {
                return $query->where($column, $allowedDivisions);
            }
            return $query;
        };

        $enrollmentQuery = DB::table('course_enrollments');
        $totalEnrollments = $applyDivision($enrollmentQuery, 'division')->count();

        $paymentPendingQuery = DB::table('payment_confirmations as pc')
            ->leftJoin('course_enrollments as ce', 'pc.enrollment_id', '=', 'ce.id')
            ->where('pc.status', 'pending');
        $pendingPaymentsCount = $applyDivision($paymentPendingQuery, 'ce.division')->count();

        $paymentVerifiedQuery = DB::table('payment_confirmations as pc')
            ->leftJoin('course_enrollments as ce', 'pc.enrollment_id', '=', 'ce.id')
            ->where('pc.status', 'verified');
        $verifiedPaymentsCount = $applyDivision($paymentVerifiedQuery, 'ce.division')->count();

        if ($allowedDivisions !== null && $allowedDivisions !== '' && $allowedDivisions !== 'all') {
            $studentQuery = DB::table('course_enrollments')
                ->whereNotNull('student_email')
                ->distinct('student_email');
            $totalStudents = $applyDivision($studentQuery, 'division')->count('student_email');
            $totalUsers = $totalStudents;
        } else {
            $totalUsers = DB::table('users')->count();
            $totalStudents = DB::table('users')->where('role', 'student')->count();
        }

        $divisionSummaryQuery = DB::table('v_division_summary');
        $divisionSummary = $applyDivision($divisionSummaryQuery, 'division')->get();

        return [
            'metrics' => [
                'total_users' => $totalUsers,
                'total_students' => $totalStudents,
                'total_enrollments' => $totalEnrollments,
                'pending_payments' => $pendingPaymentsCount,
                'verified_payments' => $verifiedPaymentsCount,
            ],
            'divisions' => $divisionSummary,
        ];
    }
}
