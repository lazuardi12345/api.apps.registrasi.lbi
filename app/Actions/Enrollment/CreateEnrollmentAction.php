<?php

namespace App\Actions\Enrollment;

use App\Models\CourseEnrollment;
use App\Models\CourseLevel;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class CreateEnrollmentAction
{
    /**
     * Create a new course enrollment.
     */
    public function execute(array $data): CourseEnrollment
    {
        return DB::transaction(function () use ($data) {
            $data['registration_track'] = $data['registration_track'] ?? 'no_test';
            $data['status'] = $data['status'] ?? ($data['registration_track'] === 'placement_test' ? 'placement_test' : 'pending');
            $data['discount_percent'] = $data['discount_percent'] ?? 0.00;
            $data['submitted_at'] = $data['submitted_at'] ?? now();

            // Auto-resolve term_id from course_schedule if not directly provided
            if (empty($data['term_id']) && !empty($data['course_schedule_id'])) {
                $schedule = \App\Models\CourseSchedule::find($data['course_schedule_id']);
                if ($schedule && $schedule->term_id) {
                    $data['term_id'] = $schedule->term_id;
                }
            }

            // If still empty, resolve current active term for division
            if (empty($data['term_id']) && !empty($data['division'])) {
                $div = strtolower($data['division']);
                $baseProgram = str_starts_with($div, 'ppb') || $div === 'testing' ? 'ppb' : ($div === 'bipa' ? 'bipa' : 'ppp');
                $currentTerm = \App\Models\Term::where(function ($q) use ($baseProgram) {
                    $q->where('program', $baseProgram)->orWhere('program', 'all');
                })->where('is_active', true)->where('is_current', true)->first();

                if ($currentTerm) {
                    $data['term_id'] = $currentTerm->id;
                }
            }

            // Sync term_period string from term if available
            if (!empty($data['term_id']) && empty($data['term_period'])) {
                $term = \App\Models\Term::find($data['term_id']);
                if ($term) {
                    $data['term_period'] = $term->name;
                }
            }

            if (($data['student_type'] ?? null) === 'lanjutan') {
                $catalogId = $data['course_catalog_id'] ?? null;
                $previousEnrollment = $catalogId
                    ? CourseEnrollment::query()
                        ->where('student_email', $data['student_email'])
                        ->where('course_catalog_id', $catalogId)
                        ->where('completion_status', 'lulus')
                        ->with('courseLevel')
                        ->latest()
                        ->first()
                    : null;
                $previousLevel = $previousEnrollment?->courseLevel;
                $nextLevel = $previousLevel
                    ? CourseLevel::where('course_catalog_id', $catalogId)
                        ->where('sort_order', '>', $previousLevel->sort_order)
                        ->orderBy('sort_order')
                        ->first()
                    : null;

                if (!$nextLevel) {
                    throw ValidationException::withMessages([
                        'student_type' => ['Peserta lanjutan hanya dapat mendaftar setelah lulus level sebelumnya dan tersedia level berikutnya.'],
                    ]);
                }

                $data['course_level_id'] = $nextLevel->id;
                $data['level_name'] = $nextLevel->name;
            }

            $enrollment = CourseEnrollment::create($data);

            // Alokasikan serial number / token tes penempatan jika jalur placement test pada PPB
            $div = strtolower($enrollment->division ?? '');
            $isPpb = str_starts_with($div, 'ppb') || $div === 'testing';
            if ($isPpb && ($enrollment->registration_track === 'placement_test' || $enrollment->status === 'placement_test')) {
                $token = \App\Models\PlacementTestToken::claimForEnrollment($enrollment);
                if ($token) {
                    $meta = $enrollment->metadata ?? [];
                    $meta['placement_test_token'] = [
                        'token_code' => $token->token_code,
                        'activation_start_date' => $token->activation_start_date?->format('Y-m-d'),
                        'expiry_date' => $token->expiry_date?->format('Y-m-d'),
                        'licence_length_days' => $token->licence_length_days,
                        'test_type' => $token->test_type,
                        'assigned_at' => $token->assigned_at?->toIso8601String(),
                        'is_hidden' => true, // Disembunyikan dari peserta hingga email resmi terkirim
                    ];
                    $enrollment->metadata = $meta;
                    $enrollment->save();
                }
            }

            return $enrollment;
        });
    }
}
