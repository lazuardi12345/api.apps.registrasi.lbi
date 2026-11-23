<?php

namespace App\Actions\Enrollment;

use App\Models\CourseEnrollment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetEnrollmentsAction
{
    /**
     * Retrieve paginated enrollments with optional division/status/email filters.
     */
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CourseEnrollment::query()->with(['payments', 'term', 'user.profile'])->latest();

        if (!empty($filters['division'])) {
            $query->where('division', $filters['division']);
        }

        if (!empty($filters['division_scope'])) {
            $query->whereIn('division', $filters['division_scope']);
        }

        if (($filters['workflow_stage'] ?? null) === 'courses') {
            $query
                ->whereNotNull('course_schedule_id')
                ->where(function ($paidQuery) {
                    $paidQuery
                        ->whereHas('payments', function ($paymentQuery) {
                            $paymentQuery
                                ->where('status', 'verified')
                                ->where(function ($typeQuery) {
                                    $typeQuery->where('payment_for', 'registration')->orWhereNull('payment_for');
                                });
                        })
                        ->orWhereNotNull('metadata->admin_workflow->payment_injected_date')
                        ->orWhere('registration_fee', '<=', 0);
                });
        } elseif (($filters['workflow_stage'] ?? null) === 'registrations') {
            $query->where(function ($stageQuery) {
                $stageQuery
                    ->whereNull('course_schedule_id')
                    ->orWhere(function ($unscheduledOrUnpaidQuery) {
                        $unscheduledOrUnpaidQuery
                            ->whereNotNull('course_schedule_id')
                            ->whereDoesntHave('payments', function ($paymentQuery) {
                                $paymentQuery
                                    ->where('status', 'verified')
                                    ->where(function ($typeQuery) {
                                        $typeQuery->where('payment_for', 'registration')->orWhereNull('payment_for');
                                    });
                            })
                            ->whereNull('metadata->admin_workflow->payment_injected_date')
                            ->where('registration_fee', '>', 0);
                    });
            });
        }

        if (!empty($filters['term_id'])) {
            $query->where('term_id', $filters['term_id']);
        }

        if (!empty($filters['term_period'])) {
            $query->where('term_period', $filters['term_period']);
        }

        if (!empty($filters['registration_track'])) {
            $query->where('registration_track', $filters['registration_track']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['student_email'])) {
            $query->where('student_email', $filters['student_email']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                  ->orWhere('student_email', 'like', "%{$search}%")
                  ->orWhere('course_name', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }
}
