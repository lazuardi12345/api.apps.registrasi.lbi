<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Enrollment\UpdateEnrollmentStatusAction;
use App\Actions\Enrollment\GetEnrollmentsAction;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CourseEnrollmentResource;
use App\Models\CourseEnrollment;
use App\Models\PaymentConfirmation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminEnrollmentController extends BaseApiController
{
    public function index(Request $request, GetEnrollmentsAction $action): JsonResponse
    {
        $validated = $request->validate([
            'workflow_stage' => ['nullable', Rule::in(['registrations', 'courses'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $filters = $request->only(['division', 'status', 'student_email', 'search', 'term_id', 'term_period', 'registration_track']);
        $filters['workflow_stage'] = $validated['workflow_stage'] ?? null;
        $filters['division_scope'] = $this->allowedDivisions($request);
        $perPage = $validated['per_page'] ?? 20;

        $enrollments = $action->execute($filters, $perPage);

        return $this->sendPaginated(CourseEnrollmentResource::collection($enrollments), 'Enrollments retrieved');
    }

    public function show(Request $request, CourseEnrollment $enrollment): JsonResponse
    {
        $this->authorizeEnrollment($request, $enrollment);

        return $this->sendSuccess(
            new CourseEnrollmentResource($enrollment->load(['payments', 'user.profile'])),
            'Enrollment detail retrieved'
        );
    }

    public function updateStatus(
        Request $request,
        CourseEnrollment $enrollment,
        UpdateEnrollmentStatusAction $action
    ): JsonResponse {
        $this->authorizeEnrollment($request, $enrollment);
        $validated = $request->validate([
            'status' => 'required|string|in:pending,pending_payment,waiting_verification,confirmed,approved,rejected,postponed,cancelled,placement_test,placement_test_failed,schedule_selection',
            'postpone_reason' => 'nullable|string|max:500',
        ]);

        $updated = $action->execute($enrollment, $validated['status'], $validated['postpone_reason'] ?? null);

        return $this->sendSuccess(
            new CourseEnrollmentResource($updated),
            'Enrollment status updated successfully'
        );
    }

    public function updateWorkflow(Request $request, CourseEnrollment $enrollment): JsonResponse
    {
        $allowedDivisions = $this->allowedDivisions($request);
        $this->authorizeEnrollment($request, $enrollment);
        $divisionOptions = $allowedDivisions ?? ['ppb', 'ppb_english', 'ppb_foreign', 'bipa', 'ppp', 'testing'];
        $validated = $request->validate([
            'enrollment_fields' => ['sometimes', 'array'],
            'enrollment_fields.division' => ['sometimes', 'required', Rule::in($divisionOptions)],
            'enrollment_fields.student_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'enrollment_fields.registration_track' => ['sometimes', 'nullable', 'in:no_test,placement_test,language_test'],
            'enrollment_fields.term_id' => ['sometimes', 'nullable', 'integer', 'exists:terms,id'],
            'enrollment_fields.term_period' => ['sometimes', 'nullable', 'string', 'max:50'],
            'enrollment_fields.course_catalog_id' => ['sometimes', 'nullable', 'integer', 'exists:course_catalogs,id'],
            'enrollment_fields.course_name' => ['sometimes', 'required', 'string', 'max:255'],
            'enrollment_fields.course_level_id' => ['sometimes', 'nullable', 'integer', 'exists:course_levels,id'],
            'enrollment_fields.level_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'enrollment_fields.course_schedule_id' => ['sometimes', 'nullable', 'integer', 'exists:course_schedules,id'],
            'enrollment_fields.campus_location' => ['sometimes', 'nullable', 'in:depok,salemba,online'],
            'enrollment_fields.learning_mode' => ['sometimes', 'nullable', 'in:daring,luring,hybrid'],
            'enrollment_fields.schedule_days' => ['sometimes', 'nullable', 'string', 'max:100'],
            'enrollment_fields.schedule_time' => ['sometimes', 'nullable', 'string', 'max:100'],
            'enrollment_fields.discount_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'enrollment_fields.registration_fee' => ['sometimes', 'numeric', 'min:0'],
            'enrollment_fields.tuition_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'participant_fields' => ['sometimes', 'array'],
            'participant_fields.student_name' => ['sometimes', 'required', 'string', 'max:255'],
            'participant_fields.student_email' => ['sometimes', 'required', 'email', 'max:191'],
            'participant_fields.student_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'participant_fields.campus_location' => ['sometimes', 'nullable', 'in:depok,salemba,online'],
            'participant_fields.metadata' => ['sometimes', 'array'],
            'participant_fields.metadata.nationality' => ['sometimes', 'nullable', 'string', 'max:50'],
            'participant_fields.metadata.identity_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'participant_fields.metadata.identity_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'participant_fields.metadata.emergency_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'participant_fields.metadata.kitas_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'participant_fields.metadata.passport_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'participant_fields.metadata.address' => ['sometimes', 'nullable', 'string'],
            'participant_fields.metadata.notes' => ['sometimes', 'nullable', 'string'],
            'participant_fields.metadata.profile' => ['sometimes', 'nullable', 'array'],
            'participant_fields.metadata.profile.birth_place' => ['sometimes', 'nullable', 'string', 'max:100'],
            'participant_fields.metadata.profile.birth_date' => ['sometimes', 'nullable', 'date'],
            'participant_fields.metadata.profile.gender' => ['sometimes', 'nullable', 'string', 'max:50'],
            'participant_fields.metadata.profile.education' => ['sometimes', 'nullable', 'string', 'max:100'],
            'participant_fields.metadata.profile.occupation' => ['sometimes', 'nullable', 'string', 'max:100'],
            'participant_fields.metadata.profile.address' => ['sometimes', 'nullable', 'string'],
            'test_complete' => ['sometimes', 'boolean'],
            'wna_verified' => ['sometimes', 'boolean'],
            'relief_requested' => ['sometimes', 'boolean'],
            'relief_approved' => ['sometimes', 'nullable', 'boolean'],
            'payment_injected_date' => ['sometimes', 'nullable', 'date'],
            'completion_status' => ['sometimes', 'nullable', 'in:lulus,tidak_lulus,dalam_proses'],
            'placement_test_result' => ['sometimes', 'nullable', 'in:lulus,tidak_lulus,tidak_perlu'],
            'course_level_id' => ['sometimes', 'nullable', 'integer', 'exists:course_levels,id'],
        ]);

        $enrollmentFields = $validated['enrollment_fields'] ?? [];
        unset($validated['enrollment_fields']);
        $participantFields = $validated['participant_fields'] ?? [];
        unset($validated['participant_fields']);
        $participantMetadata = $participantFields['metadata'] ?? null;
        unset($participantFields['metadata']);

        $completionStatus = $validated['completion_status'] ?? null;
        $hasCompletionStatus = array_key_exists('completion_status', $validated);
        unset($validated['completion_status']);
        $placementTestResult = $validated['placement_test_result'] ?? null;
        $hasPlacementTestResult = array_key_exists('placement_test_result', $validated);
        unset($validated['placement_test_result']);
        $courseLevelId = $enrollmentFields['course_level_id'] ?? $validated['course_level_id'] ?? null;
        $hasCourseLevel = array_key_exists('course_level_id', $enrollmentFields) || array_key_exists('course_level_id', $validated);
        unset($enrollmentFields['course_level_id']);
        unset($validated['course_level_id']);
        $metadata = $enrollment->metadata ?? [];
        if (is_array($participantMetadata)) {
            foreach (['admin_workflow', 'identity_documents', 'bipa_documents'] as $protectedKey) {
                unset($participantMetadata[$protectedKey]);
            }
            $metadata = array_merge($metadata, $participantMetadata);
        }

        if (array_key_exists('relief_requested', $validated)) {
            $reliefRequested = filter_var($validated['relief_requested'], FILTER_VALIDATE_BOOLEAN);
            $validated['relief_requested'] = $reliefRequested;
            $metadata['relief_requested'] = $reliefRequested;
        }
        if (array_key_exists('relief_approved', $validated)) {
            $validated['relief_approved'] = filter_var($validated['relief_approved'], FILTER_VALIDATE_BOOLEAN);
        }

        $metadata['admin_workflow'] = array_merge($metadata['admin_workflow'] ?? [], $validated);
        $updates = array_merge($enrollmentFields, $participantFields, ['metadata' => $metadata]);
        if ($hasCompletionStatus) $updates['completion_status'] = $completionStatus;
        if ($hasCourseLevel) {
            $updates['course_level_id'] = $courseLevelId;
            $updates['level_name'] = $courseLevelId ? \App\Models\CourseLevel::find($courseLevelId)?->name : null;
        }
        if ($hasPlacementTestResult) {
            $metadata['admin_workflow']['placement_test_result'] = $placementTestResult;
            $updates['metadata'] = $metadata;
            if ($placementTestResult === 'tidak_lulus') {
                $updates['status'] = 'placement_test_failed';
            } elseif (in_array($placementTestResult, ['lulus', 'tidak_perlu'], true) && $courseLevelId) {
                $updates['status'] = 'schedule_selection';
            }
        } elseif ($hasCourseLevel && $courseLevelId && (
            $enrollment->registration_track === 'no_test'
            || in_array(data_get($metadata, 'admin_workflow.placement_test_result'), ['lulus', 'tidak_perlu'], true)
        )) {
            $updates['status'] = 'schedule_selection';
        }
        $enrollment->update($updates);

        // Keep StudentProfile synchronized with updated participant fields
        $userId = $enrollment->user_id;
        if (!$userId && $enrollment->student_email) {
            $foundUser = \App\Models\User::where('email', $enrollment->student_email)->first();
            if ($foundUser) {
                $userId = $foundUser->id;
                $enrollment->update(['user_id' => $userId]);
            }
        }

        if ($userId) {
            $profileData = array_filter([
                'full_name' => $updates['student_name'] ?? $enrollment->student_name,
                'identity_number' => data_get($metadata, 'identity_number') ?? data_get($metadata, 'profile.identity_number'),
                'phone' => $updates['student_phone'] ?? $enrollment->student_phone,
                'birth_place' => data_get($metadata, 'profile.birth_place'),
                'birth_date' => data_get($metadata, 'profile.birth_date'),
                'gender' => data_get($metadata, 'profile.gender'),
                'education' => data_get($metadata, 'profile.education'),
                'occupation' => data_get($metadata, 'profile.occupation'),
                'address' => data_get($metadata, 'profile.address') ?? data_get($metadata, 'address'),
            ], fn($v) => !is_null($v) && $v !== '');

            if (!empty($profileData)) {
                \App\Models\StudentProfile::updateOrCreate(
                    ['user_id' => $userId],
                    $profileData
                );
            }
        }

        return $this->sendSuccess(
            new CourseEnrollmentResource($enrollment->fresh(['payments', 'user.profile'])),
            'Data administrasi pendaftaran berhasil diperbarui.'
        );
    }

    public function injectPayment(Request $request, CourseEnrollment $enrollment): JsonResponse
    {
        $this->authorizeEnrollment($request, $enrollment);

        $validated = $request->validate([
            'payment_for'      => ['required', 'in:registration,tuition'],
            'payment_date'     => ['required', 'date'],
            'amount'           => ['required', 'numeric', 'min:0'],
            'channel'          => ['required', 'string'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'note'             => ['nullable', 'string', 'max:500'],
        ]);

        $channelLabels = [
            'cash'          => 'Kasir Tunai LBI FIB UI',
            'edc'           => 'EDC Kartu Kantor LBI FIB UI',
            'bni_va_manual' => 'BNI Virtual Account (Manual Inject/Maintenance)',
            'xendit_manual' => 'Xendit Gateway (Manual Inject/Maintenance)',
            'bank_transfer' => 'Transfer Bank Offline / Teller',
            'other'         => 'Pembayaran Manual / Kasir',
        ];

        $bankName = $channelLabels[$validated['channel']] ?? $validated['channel'];
        $admin = $request->user('api');
        $user = $enrollment->user ?? User::where('email', $enrollment->student_email)->first();

        // 1. Create or update payment_confirmations record
        $payment = PaymentConfirmation::updateOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'payment_for'   => $validated['payment_for'],
            ],
            [
                'user_id'        => $enrollment->user_id ?? $user?->id,
                'student_name'   => $enrollment->student_name,
                'student_email'  => $enrollment->student_email,
                'course_name'    => $enrollment->course_name,
                'bank_name'      => $bankName,
                'account_number' => $validated['reference_number'] ?: ('INJ-' . strtoupper(uniqid())),
                'account_holder' => $enrollment->student_name,
                'amount'         => $validated['amount'],
                'payment_date'   => $validated['payment_date'],
                'status'         => 'verified',
                'verified_at'    => now(),
                'verified_by'    => $admin?->id,
                'verified_note'  => $validated['note'] ?: "Inject lunas oleh Admin ({$admin?->name}) via {$bankName}",
            ]
        );

        // 2. Update metadata & enrollment fields
        $metadata = $enrollment->metadata ?? [];
        $metadata['admin_workflow']['payment_injected_date'] = $validated['payment_date'];
        $metadata['admin_workflow']['payment_injected_by'] = $admin?->id;
        $metadata['admin_workflow']['payment_injected_channel'] = $validated['channel'];
        $metadata['admin_workflow']['payment_injected_ref'] = $validated['reference_number'] ?? null;
        $metadata['admin_workflow']['payment_injected_note'] = $validated['note'] ?? null;

        $updates = ['metadata' => $metadata];

        if ($validated['payment_for'] === 'tuition') {
            $updates['tuition_fee'] = $validated['amount'];
            $updates['payment_status'] = 'paid';
            if (in_array($enrollment->status, ['pending_payment', 'waiting_verification', 'draft'], true)) {
                $updates['status'] = 'confirmed';
            }
        } else {
            $updates['registration_fee'] = $validated['amount'];
            $updates['registration_fee_status'] = 'paid';
            if ($enrollment->registration_track === 'placement_test' && in_array($enrollment->status, ['pending_payment', 'waiting_verification', 'draft'], true)) {
                $updates['status'] = 'placement_test';
            } elseif ($enrollment->registration_track === 'no_test' && in_array($enrollment->status, ['pending_payment', 'waiting_verification', 'draft'], true)) {
                $updates['status'] = 'schedule_selection';
            }
        }

        $enrollment->update($updates);

        return $this->sendSuccess(
            new CourseEnrollmentResource($enrollment->fresh(['payments', 'user.profile'])),
            'Pembayaran berhasil di-inject lunas dan disinkronkan ke Finance.'
        );
    }

    public function updatePaymentInjectedDate(Request $request, CourseEnrollment $enrollment): JsonResponse
    {
        $this->authorizeEnrollment($request, $enrollment);
        $validated = $request->validate([
            'payment_injected_date' => ['nullable', 'date'],
            'payment_for'           => ['nullable', 'in:registration,tuition'],
            'amount'                => ['nullable', 'numeric'],
            'channel'               => ['nullable', 'string'],
            'reference_number'      => ['nullable', 'string'],
            'note'                  => ['nullable', 'string'],
        ]);

        $date = $validated['payment_injected_date'] ?? null;
        if ($date) {
            $paymentFor = $validated['payment_for'] ?? ($enrollment->tuition_fee ? 'tuition' : 'registration');
            $amount = $validated['amount'] ?? ($paymentFor === 'tuition' ? ($enrollment->tuition_fee ?: 0) : ($enrollment->registration_fee ?: 160000));
            $channel = $validated['channel'] ?? 'other';

            $subRequest = new Request([
                'payment_for'      => $paymentFor,
                'payment_date'     => $date,
                'amount'           => $amount,
                'channel'          => $channel,
                'reference_number' => $validated['reference_number'] ?? null,
                'note'             => $validated['note'] ?? 'Inject pembayaran manual',
            ]);
            $subRequest->setUserResolver($request->getUserResolver());

            return $this->injectPayment($subRequest, $enrollment);
        }

        $metadata = $enrollment->metadata ?? [];
        $metadata['admin_workflow']['payment_injected_date'] = null;
        $enrollment->update(['metadata' => $metadata]);

        return $this->sendSuccess(
            new CourseEnrollmentResource($enrollment->fresh(['payments', 'user.profile'])),
            'Tanggal pembayaran manual berhasil diperbarui.'
        );
    }

    public function downloadIdentityDocument(CourseEnrollment $enrollment, string $document): mixed
    {
        $this->authorizeEnrollment(request(), $enrollment);
        $isBipaDocument = in_array($document, ['bipa_photo', 'bipa_passport_copy', 'bipa_sponsor_letter'], true);
        abort_unless($isBipaDocument || in_array($document, ['identity_document', 'kitas_document', 'passport_document'], true), 404);

        $path = $isBipaDocument
            ? data_get($enrollment->metadata, "bipa_documents.{$document}")
            : data_get($enrollment->metadata, "identity_documents.{$document}");
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    private function allowedDivisions(Request $request): ?array
    {
        $user = $request->user('api');
        abort_unless($user, 401);

        if ($user->hasRole('administrator')) {
            return null;
        }

        $divisions = [];
        if ($user->hasRole('admin_ppb')) {
            $divisions = array_merge($divisions, ['ppb', 'ppb_english', 'ppb_foreign', 'testing']);
        }
        if ($user->hasRole('admin_bipa')) {
            $divisions[] = 'bipa';
        }
        if ($user->hasRole('admin_ppp')) {
            $divisions[] = 'ppp';
        }

        abort_if($divisions === [], 403, 'You are not authorized to access enrollment records.');

        return array_values(array_unique($divisions));
    }

    private function authorizeEnrollment(Request $request, CourseEnrollment $enrollment): void
    {
        $allowedDivisions = $this->allowedDivisions($request);
        abort_if(
            $allowedDivisions !== null && ! in_array($enrollment->division, $allowedDivisions, true),
            404
        );
    }
}
