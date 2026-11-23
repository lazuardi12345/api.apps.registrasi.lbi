<?php

namespace App\Http\Controllers\Api\Student;

use App\Actions\Fees\ResolveCourseFeeAction;
use App\Actions\Enrollment\ContinueEnrollmentAction;
use App\Actions\Enrollment\PostponeEnrollmentAction;
use App\Actions\Enrollment\TransferEnrollmentAction;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CourseEnrollmentResource;
use App\Http\Resources\PaymentConfirmationResource;
use App\Http\Resources\UserResource;
use Illuminate\Database\Eloquent\Builder;
use App\Models\CourseEnrollment;
use App\Models\CourseSchedule;
use App\Models\PaymentConfirmation;
use App\Http\Resources\CourseScheduleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentPortalController extends BaseApiController
{
    /**
     * GET /api/v1/student/my-enrollments
     */
    public function myEnrollments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $user        = $request->user();
        $enrollments = CourseEnrollment::with(['payments', 'courseCatalog', 'courseLevel', 'courseSchedule', 'term'])
            ->where(function ($q) use ($user) {
                $q->where('student_email', $user->email)
                  ->orWhere('user_id', $user->id);
            })
            ->latest()
            ->paginate($validated['per_page'] ?? 15);

        return $this->sendPaginated(
            CourseEnrollmentResource::collection($enrollments),
            'My enrollments retrieved'
        );
    }

    /**
     * GET /api/v1/student/my-payments
     */
    public function myPayments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $user     = $request->user();
        $payments = PaymentConfirmation::with('enrollment')
            ->where(fn ($q) => $q
                ->where('user_id', $user->id)
                ->orWhere('student_email', $user->email)
            )
            ->latest()
            ->paginate($validated['per_page'] ?? 15);

        return $this->sendPaginated(
            PaymentConfirmationResource::collection($payments),
            'My payments retrieved'
        );
    }

    public function availableSchedules(Request $request, int $id, ResolveCourseFeeAction $feeResolver): JsonResponse
    {
        $user = $request->user();
        $enrollment = CourseEnrollment::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('student_email', $user->email)
                  ->orWhere('user_id', $user->id);
            })
            ->firstOrFail();

        if ($enrollment->status !== 'schedule_selection' || ! $enrollment->course_catalog_id || ! $enrollment->course_level_id) {
            return $this->sendError('Level kursus belum ditetapkan untuk pemilihan jadwal.', 422);
        }

        $schedules = $this->availableSchedulesForEnrollment($enrollment);

        $scheduleOptions = $schedules->map(function (CourseSchedule $schedule) use ($feeResolver, $request, $enrollment) {
            $feeRule = $feeResolver->execute([
                'fee_type' => 'tuition',
                'division' => $schedule->division,
                'course_catalog_id' => $schedule->course_catalog_id,
                'course_level_id' => $schedule->course_level_id,
                'course_schedule_id' => $schedule->id,
                'term_id' => $schedule->term_id,
            ]);
            $baseAmount = $feeRule ? (float) $feeRule->amount : (float) ($schedule->fee ?? 0);
            $discountPercent = $this->approvedDiscountPercent($enrollment);
            $amount = round($baseAmount * (1 - $discountPercent / 100), 2);
            $feeConfigured = $feeRule !== null || $schedule->fee !== null;
            $resource = (new CourseScheduleResource($schedule))->toArray($request);
            $resource['fee'] = $amount;
            $resource['fee_before_discount'] = $baseAmount;
            $resource['fee_discount_percent'] = $discountPercent;
            $resource['fee_configured'] = $feeConfigured;
            $resource['fee_label'] = $feeConfigured
                ? ($amount > 0 ? 'Rp ' . number_format($amount, 0, ',', '.') : 'Gratis')
                : 'Tarif belum diatur';
            return $resource;
        });

        return $this->sendSuccess($scheduleOptions, 'Jadwal untuk level kursus tersedia.');
    }

    public function selectSchedule(Request $request, int $id, ResolveCourseFeeAction $feeResolver): JsonResponse
    {
        $validated = $request->validate([
            'schedule_id' => ['required', 'integer', 'exists:course_schedules,id'],
        ]);

        $user = $request->user();
        $enrollment = CourseEnrollment::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('student_email', $user->email)
                  ->orWhere('user_id', $user->id);
            })
            ->firstOrFail();

        if ($enrollment->status !== 'schedule_selection' || ! $enrollment->course_level_id) {
            return $this->sendError('Pendaftaran belum siap memilih jadwal.', 422);
        }

        try {
            $updated = DB::transaction(function () use ($enrollment, $validated, $feeResolver) {
                $schedule = $this->availableSchedulesForEnrollmentQuery($enrollment)
                    ->whereKey($validated['schedule_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $schedule) {
                    throw new \InvalidArgumentException('Jadwal tidak tersedia untuk program dan level yang ditetapkan.');
                }

                $feeRule = $feeResolver->execute([
                    'fee_type' => 'tuition',
                    'division' => $schedule->division,
                    'course_catalog_id' => $schedule->course_catalog_id,
                    'course_level_id' => $schedule->course_level_id,
                    'course_schedule_id' => $schedule->id,
                    'term_id' => $schedule->term_id,
                ]);
                $baseTuitionFee = $feeRule
                    ? (float) $feeRule->amount
                    : ($schedule->fee !== null ? (float) $schedule->fee : null);
                if ($baseTuitionFee === null) {
                    throw new \InvalidArgumentException('Tarif kursus untuk jadwal ini belum diatur di Master Tarif. Silakan hubungi admin.');
                }
                $tuitionFee = round(
                    $baseTuitionFee * (1 - $this->approvedDiscountPercent($enrollment) / 100),
                    2
                );
                $format = strtolower((string) ($schedule->format ?? ''));
                $learningMode = in_array($format, ['online', 'daring'], true) ? 'daring' : (in_array($format, ['hybrid'], true) ? 'hybrid' : 'luring');
                $locationCode = strtolower((string) ($schedule->classLocation?->code ?? ''));
                $campusLocation = in_array($locationCode, ['depok', 'salemba'], true)
                    ? $locationCode
                    : ($learningMode === 'daring' ? 'online' : 'depok');

                $schedule->increment('registered');
                $enrollment->update([
                    'course_schedule_id' => $schedule->id,
                    'course_fee_rule_id' => $feeRule?->id,
                    'term_id' => $schedule->term_id,
                    'term_period' => $schedule->term?->name ?? $schedule->batch,
                    'course_name' => $this->isPpbOrPpp($enrollment)
                        ? ($enrollment->course_name ?: $schedule->program_name)
                        : $schedule->program_name,
                    'level_name' => $schedule->courseLevel?->name ?? $schedule->level,
                    'schedule_days' => $schedule->scheduleDay?->name ?? $schedule->schedule_text,
                    'schedule_time' => $schedule->scheduleTime?->time_range,
                    'learning_mode' => $learningMode,
                    'campus_location' => $campusLocation,
                    'tuition_fee' => $tuitionFee,
                    'status' => 'pending_payment',
                    'payment_status' => 'pending',
                ]);

                return $enrollment->fresh(['term', 'courseCatalog', 'courseLevel', 'courseSchedule']);
            });

            return $this->sendSuccess(new CourseEnrollmentResource($updated), 'Jadwal dipilih. Silakan lanjutkan pembayaran kursus.');
        } catch (\InvalidArgumentException $error) {
            return $this->sendError($error->getMessage(), 422);
        }
    }

    /**
     * PUT /api/v1/student/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'            => 'sometimes|required|string|max:191',
            'phone'           => 'nullable|string|max:50',
            'identity_number' => 'nullable|string|max:100',
            'gender'          => 'nullable|string|in:L,P,male,female,Laki-laki,Perempuan',
            'birth_place'     => 'nullable|string|max:100',
            'birth_date'      => 'nullable|date',
            'education'       => 'nullable|string|max:100',
            'occupation'      => 'nullable|string|max:100',
            'address'         => 'nullable|string',
            'photo_url'       => 'nullable|string',
            'photo'           => 'nullable|image|max:3072',
        ]);

        if (isset($validated['name'])) {
            $user->update(['name' => $validated['name']]);
        }

        $profileData = collect($validated)->except(['name', 'photo'])->filter(fn($val) => !is_null($val))->all();

        // Handle uploaded photo file
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('avatars', 'public');
            $profileData['photo_url'] = '/storage/' . $path;
        }

        $user->profile()->updateOrCreate(['user_id' => $user->id], $profileData);

        return $this->sendSuccess(
            new UserResource($user->load(['profile', 'roles'])),
            'Profile updated successfully'
        );
    }

    /* ------------------------------------------------------------------ */
    /*  FASE 3D - Workflow: Postpone / Continue / Transfer                  */
    /* ------------------------------------------------------------------ */

    /**
     * POST /api/v1/student/enrollments/{id}/postpone
     */
    public function postpone(Request $request, int $id, PostponeEnrollmentAction $action): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        $user = $request->user();
        $enrollment = CourseEnrollment::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('student_email', $user->email)
                  ->orWhere('user_id', $user->id);
            })
            ->firstOrFail();

        try {
            $updated = $action->execute($enrollment, $validated['reason']);
            return $this->sendSuccess(
                new CourseEnrollmentResource($updated),
                'Enrollment berhasil ditunda.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v1/student/enrollments/{id}/continue
     */
    public function continue(Request $request, int $id, ContinueEnrollmentAction $action): JsonResponse
    {
        $user = $request->user();
        $enrollment = CourseEnrollment::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('student_email', $user->email)
                  ->orWhere('user_id', $user->id);
            })
            ->firstOrFail();

        try {
            $updated = $action->execute($enrollment);
            return $this->sendSuccess(
                new CourseEnrollmentResource($updated->load('payments')),
                'Enrollment berhasil dilanjutkan kembali.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/v1/student/enrollments/{id}/transfer
     */
    public function transfer(Request $request, int $id, TransferEnrollmentAction $action): JsonResponse
    {
        $validated = $request->validate([
            'target_division' => 'required|string|in:ppb,ppb_foreign,ppp,bipa',
            'reason'          => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $enrollment = CourseEnrollment::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('student_email', $user->email)
                  ->orWhere('user_id', $user->id);
            })
            ->firstOrFail();

        try {
            $newEnrollment = $action->execute(
                $enrollment,
                $validated['target_division'],
                $validated['reason'] ?? null
            );
            return $this->sendSuccess(
                new CourseEnrollmentResource($newEnrollment),
                "Transfer ke program '{$validated['target_division']}' berhasil. Enrollment baru telah dibuat."
            );
        } catch (\InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), 422);
        }
    }

    private function approvedDiscountPercent(CourseEnrollment $enrollment): float
    {
        if (! $this->isPpbOrPpp($enrollment)) {
            return 0;
        }

        $reliefApproved = filter_var(
            data_get($enrollment->metadata, 'admin_workflow.relief_approved', false),
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $reliefApproved) {
            return 0;
        }

        return max(0, min(100, (float) $enrollment->discount_percent));
    }

    private function isPpbOrPpp(CourseEnrollment $enrollment): bool
    {
        $division = strtolower((string) $enrollment->division);
        return str_starts_with($division, 'ppb') || $division === 'ppp';
    }

    private function availableSchedulesForEnrollment(CourseEnrollment $enrollment)
    {
        $query = $this->availableSchedulesForEnrollmentQuery($enrollment);
        $schedules = (clone $query)->get();

        if ($schedules->isEmpty() && $enrollment->term_id) {
            return $this->availableSchedulesForEnrollmentQuery($enrollment, false)->get();
        }

        return $schedules;
    }

    private function availableSchedulesForEnrollmentQuery(CourseEnrollment $enrollment, bool $filterByTerm = true): Builder
    {
        $query = CourseSchedule::query()
            ->active()
            ->where('is_open', true)
            ->whereColumn('registered', '<', 'capacity')
            ->where('course_catalog_id', $enrollment->course_catalog_id)
            ->where('course_level_id', $enrollment->course_level_id)
            ->with(['term', 'courseCatalog', 'courseLevel', 'scheduleDay', 'scheduleTime', 'learningFormat', 'classLocation'])
            ->orderBy('sort_order');

        if ($filterByTerm && $enrollment->term_id) {
            $query->where('term_id', $enrollment->term_id);
        }

        if ($filterByTerm && $enrollment->term_id) {
            $matchingTermExists = CourseSchedule::query()
                ->active()
                ->where('is_open', true)
                ->whereColumn('registered', '<', 'capacity')
                ->where('course_catalog_id', $enrollment->course_catalog_id)
                ->where('course_level_id', $enrollment->course_level_id)
                ->where('term_id', $enrollment->term_id)
                ->exists();

            if (! $matchingTermExists) {
                return $this->availableSchedulesForEnrollmentQuery($enrollment, false);
            }
        }

        return $query;
    }
}
