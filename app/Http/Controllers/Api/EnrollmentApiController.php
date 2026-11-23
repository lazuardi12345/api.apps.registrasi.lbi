<?php

namespace App\Http\Controllers\Api;

use App\Actions\Enrollment\CreateEnrollmentAction;
use App\Actions\Enrollment\GetEnrollmentsAction;
use App\Actions\Fees\ResolveCourseFeeAction;
use App\Http\Requests\Enrollment\StoreEnrollmentRequest;
use App\Http\Resources\CourseEnrollmentResource;
use App\Models\CourseCatalog;
use App\Models\CourseEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EnrollmentApiController extends BaseApiController
{
    /**
     * List enrollments with filters.
     */
    public function index(Request $request, GetEnrollmentsAction $action): JsonResponse
    {
        $filters = $request->only(['division', 'status', 'student_email', 'search']);
        $perPage = (int) $request->query('per_page', 15);

        $enrollments = $action->execute($filters, $perPage);

        return $this->sendPaginated(
            CourseEnrollmentResource::collection($enrollments),
            'Enrollments retrieved successfully'
        );
    }

    public function store(StoreEnrollmentRequest $request, CreateEnrollmentAction $action): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['user_id']) && $request->user('api')) {
            $data['user_id'] = $request->user('api')->id;
        }
        $enrollment = $action->execute($data);

        return $this->sendSuccess(
            new CourseEnrollmentResource($enrollment),
            'Course enrollment submitted successfully',
            201
        );
    }

    public function storeForProgram(
        string $program,
        StoreEnrollmentRequest $request,
        CreateEnrollmentAction $action,
        ResolveCourseFeeAction $feeResolver
    ): JsonResponse {
        $program = strtolower($program);

        if (!in_array($program, ['ppb', 'ppp', 'bipa'], true)) {
            return $this->sendError('Program enrollment is not supported.', 404);
        }

        $data = $request->validated();
        $data['division'] = $program;
        $feeType = 'registration';
        $feeRule = $feeResolver->execute([
            'fee_type' => $feeType,
            'division' => $program,
            'course_catalog_id' => $data['course_catalog_id'] ?? null,
            'term_id' => $data['term_id'] ?? null,
        ]);

        if (! $feeRule) {
            return $this->sendError('Tarif registrasi belum dikonfigurasi oleh admin.', 422);
        }

        $selectedSchedule = null;
        if (!empty($data['course_schedule_id'])) {
            $selectedSchedule = \App\Models\CourseSchedule::active()
                ->open()
                ->with(['courseLevel', 'scheduleDay', 'scheduleTime', 'learningFormat', 'classLocation'])
                ->whereKey($data['course_schedule_id'])
                ->where('course_catalog_id', $data['course_catalog_id'] ?? null)
                ->where('term_id', $data['term_id'] ?? null)
                ->where(function ($query) use ($program) {
                    if ($program === 'ppb') {
                        $query->whereIn('division', ['ppb', 'ppb_english', 'ppb_foreign']);
                    } else {
                        $query->where('division', $program);
                    }
                })
                ->first();

            if (!$selectedSchedule) {
                throw ValidationException::withMessages([
                    'course_schedule_id' => ['Jadwal tidak tersedia untuk program, katalog, atau term yang dipilih.'],
                ]);
            }
        }

        if ($program === 'ppb'
            && ($data['registration_track'] ?? null) === 'placement_test'
            && !in_array($data['campus_location'] ?? null, ['depok', 'salemba'], true)) {
            throw ValidationException::withMessages([
                'campus_location' => ['Pilih lokasi kursus Depok atau Salemba.'],
            ]);
        }

        $data['registration_fee'] = (float) $feeRule->amount;
        $data['course_level_id'] = $selectedSchedule?->course_level_id;
        $data['course_schedule_id'] = $selectedSchedule?->id;
        $data['level_name'] = $selectedSchedule?->courseLevel?->name;
        $data['schedule_days'] = $selectedSchedule?->scheduleDay?->name ?? $selectedSchedule?->schedule_text;
        $data['schedule_time'] = $selectedSchedule?->scheduleTime?->time_range;
        $data['learning_mode'] = $selectedSchedule?->learningFormat?->code ?? $selectedSchedule?->format;
        $locationText = strtolower(($selectedSchedule?->classLocation?->name ?? '') . ' ' . ($selectedSchedule?->location ?? ''));
        $data['campus_location'] = $selectedSchedule
            ? (str_contains($locationText, 'salemba') ? 'salemba' : (str_contains($locationText, 'depok') ? 'depok' : null))
            : ($program === 'ppb' ? ($data['campus_location'] ?? null) : null);
        $data['tuition_fee'] = null;
        $data['status'] = ($data['registration_track'] ?? null) === 'placement_test'
            ? 'placement_test'
            : (($data['student_type'] ?? null) === 'lanjutan' ? 'schedule_selection' : 'awaiting_level');

        $metadata = $data['metadata'] ?? [];
        $metadata['registration_fee_type'] = $feeType;
        $metadata['registration_fee_rule_id'] = $feeRule->id;
        $metadata['nationality'] = $data['nationality'] ?? ($metadata['nationality'] ?? null);
        $metadata['identity_type'] = $data['identity_type'] ?? ($metadata['identity_type'] ?? null);
        $metadata['kitas_number'] = $data['kitas_number'] ?? ($metadata['kitas_number'] ?? null);
        $metadata['passport_number'] = $data['passport_number'] ?? ($metadata['passport_number'] ?? null);
        $metadata['emergency_phone'] = $data['emergency_phone'] ?? ($metadata['emergency_phone'] ?? null);
        if (!isset($metadata['identity_documents'])) {
            $metadata['identity_documents'] = [];
        }

        foreach (['identity_document', 'kitas_document', 'passport_document'] as $documentKey) {
            if ($request->hasFile($documentKey)) {
                $metadata['identity_documents'][$documentKey] = $request->file($documentKey)
                    ->store('enrollment-documents', 'local');
            }
            unset($data[$documentKey]);
        }

        $metadata['bipa_documents'] = $metadata['bipa_documents'] ?? [];
        foreach (['bipa_photo', 'bipa_passport_copy', 'bipa_sponsor_letter'] as $documentKey) {
            if ($request->hasFile($documentKey)) {
                $metadata['bipa_documents'][$documentKey] = $request->file($documentKey)
                    ->store('enrollment-documents', 'local');
            }
            unset($data[$documentKey]);
        }

        unset($data['identity_type'], $data['kitas_number'], $data['passport_number'], $data['emergency_phone']);
        $data['metadata'] = $metadata;

        $user = null;
        if (!empty($data['user_id'])) {
            $user = \App\Models\User::find($data['user_id']);
        } elseif ($request->user('api')) {
            $user = $request->user('api');
            $data['user_id'] = $user->id;
        } elseif (!empty($data['student_email'])) {
            $user = \App\Models\User::where('email', $data['student_email'])->first();
            if ($user) {
                $data['user_id'] = $user->id;
            } else {
                $user = \App\Models\User::create([
                    'name' => $data['student_name'],
                    'email' => $data['student_email'],
                    'password' => bcrypt('LbiUI2026!'),
                    'role' => 'student',
                    'status' => true,
                ]);
                $data['user_id'] = $user->id;
            }
        }

        // Always sync submitted personal data into StudentProfile
        if ($user) {
            $profileData = array_filter([
                'full_name' => $data['student_name'],
                'identity_number' => $data['identity_number'] ?? data_get($metadata, 'identity_number') ?? data_get($metadata, 'profile.identity_number'),
                'phone' => $data['student_phone'] ?? null,
                'birth_place' => data_get($metadata, 'profile.birth_place') ?? $data['birth_place'] ?? null,
                'birth_date' => data_get($metadata, 'profile.birth_date') ?? $data['birth_date'] ?? null,
                'gender' => data_get($metadata, 'profile.gender') ?? $data['gender'] ?? null,
                'education' => data_get($metadata, 'profile.education') ?? $data['education'] ?? null,
                'occupation' => data_get($metadata, 'profile.occupation') ?? $data['occupation'] ?? null,
                'address' => data_get($metadata, 'profile.address') ?? data_get($metadata, 'address') ?? $data['address'] ?? null,
            ], fn($v) => !is_null($v) && $v !== '');

            if (!empty($profileData)) {
                \App\Models\StudentProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData
                );
            }
        }

        $enrollment = $action->execute($data);

        return $this->sendSuccess(
            new CourseEnrollmentResource($enrollment),
            strtoupper($program) . ' enrollment submitted successfully',
            201
        );
    }

    public function storeForLanguageTest(
        StoreEnrollmentRequest $request,
        CreateEnrollmentAction $action,
        ResolveCourseFeeAction $feeResolver
    ): JsonResponse {
        $data = $request->validated();
        $catalog = CourseCatalog::query()
            ->whereKey($data['course_catalog_id'] ?? null)
            ->where('division', 'testing')
            ->where('category', 'language_test')
            ->where('is_active', true)
            ->firstOrFail();

        $feeRule = $feeResolver->execute([
            'fee_type' => 'registration',
            'division' => 'testing',
            'course_catalog_id' => $catalog->id,
        ]);

        if (! $feeRule) {
            return $this->sendError('Tarif tes bahasa belum dikonfigurasi oleh admin.', 422);
        }

        $data['division'] = 'testing';
        $data['registration_track'] = 'language_test';
        $data['course_name'] = $catalog->title;
        $data['registration_fee'] = (float) $feeRule->amount;
        $data['tuition_fee'] = null;
        $data['course_level_id'] = null;
        $data['course_schedule_id'] = null;
        $data['status'] = 'pending_payment';
        $metadata = $data['metadata'] ?? [];
        $metadata['registration_fee_type'] = 'registration';
        $metadata['registration_fee_rule_id'] = $feeRule->id;
        $data['metadata'] = $metadata;

        if (empty($data['user_id'])) {
            if ($request->user('api')) {
                $data['user_id'] = $request->user('api')->id;
            } elseif (! empty($data['student_email'])) {
                $data['user_id'] = \App\Models\User::where('email', $data['student_email'])->value('id');
            }
        }

        $enrollment = $action->execute($data);

        return $this->sendSuccess(
            new CourseEnrollmentResource($enrollment),
            'Language test registration submitted successfully',
            201
        );
    }

    /**
     * Show single enrollment details.
     */
    public function show(int $id): JsonResponse
    {
        $enrollment = CourseEnrollment::with('payments')->findOrFail($id);

        return $this->sendSuccess(
            new CourseEnrollmentResource($enrollment),
            'Enrollment detail retrieved successfully'
        );
    }
}
