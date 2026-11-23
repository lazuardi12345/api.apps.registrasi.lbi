<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CourseEnrollmentResource;
use App\Http\Resources\PaymentConfirmationResource;
use App\Http\Resources\UserResource;
use App\Models\CourseEnrollment;
use App\Models\PaymentConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentDashboardController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $enrollments = CourseEnrollment::with(['payments', 'courseCatalog', 'courseLevel', 'courseSchedule', 'term'])
            ->where(function ($query) use ($user) {
                $query->where('student_email', $user->email)
                    ->orWhere('user_id', $user->id);
            })
            ->latest()
            ->get();

        $summary = [
            'total' => $enrollments->count(),
            'approved' => $enrollments->where('status', 'approved')->count(),
            'pending_payment' => $enrollments->where('status', 'pending_payment')->count(),
            'postponed' => $enrollments->where('status', 'postponed')->count(),
            'completed' => $enrollments->whereNotNull('completion_status')->count(),
            'needs_attention' => $enrollments->filter(function ($enrollment) {
                return in_array(strtolower($enrollment->status ?? ''), [
                    'pending',
                    'pending_payment',
                    'placement_test',
                    'awaiting_level',
                    'schedule_selection',
                    'awaiting_test_result',
                ], true) || $enrollment->payments->contains(function ($payment) {
                    return ! in_array(strtolower($payment->status ?? ''), ['paid', 'verified'], true);
                });
            })->count(),
        ];

        $activePayment = PaymentConfirmation::with('enrollment')
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('student_email', $user->email);
            })
            ->whereIn('status', ['pending', 'Pending'])
            ->latest()
            ->first();

        return $this->sendSuccess([
            'student' => new UserResource($user->load('profile')),
            'summary' => $summary,
            'enrollments' => CourseEnrollmentResource::collection($enrollments),
            'recent_enrollments' => CourseEnrollmentResource::collection($enrollments->take(5)->values()),
            'active_payment' => $activePayment ? new PaymentConfirmationResource($activePayment) : null,
        ], 'Dashboard data retrieved');
    }
}
