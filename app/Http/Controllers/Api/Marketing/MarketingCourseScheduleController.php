<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CourseScheduleResource;
use App\Models\CourseSchedule;
use App\Models\CourseLevel;
use App\Models\ScheduleDay;
use App\Models\ScheduleTime;
use App\Models\LearningFormat;
use App\Models\ClassLocation;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketingCourseScheduleController extends BaseApiController
{
    private const RELATIONS = [
        'service',
        'courseCatalog',
        'term',
        'courseLevel',
        'scheduleDay',
        'scheduleTime',
        'learningFormat',
        'classLocation'
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'course_catalog_id' => ['sometimes', 'integer', 'exists:course_catalogs,id'],
            'format' => ['sometimes', 'in:all,daring,luring,hybrid,online,offline'],
            'search' => ['sometimes', 'string', 'max:255'],
        ]);
        $divisions = $this->allowedDivisions($request);
        $query = CourseSchedule::with(self::RELATIONS);
        if ($divisions !== null) {
            $query->whereIn('division', $divisions);
        }

        if (! empty($validated['course_catalog_id'])) {
            $query->where('course_catalog_id', $validated['course_catalog_id']);
        }

        if (! empty($validated['format']) && $validated['format'] !== 'all') {
            $formats = match ($validated['format']) {
                'daring', 'online' => ['daring', 'online'],
                'luring', 'offline' => ['luring', 'offline'],
                default => [$validated['format']],
            };
            $query->whereIn('format', $formats);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('program_name', 'like', "%{$search}%")
                    ->orWhere('batch', 'like', "%{$search}%")
                    ->orWhere('level', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('schedule_text', 'like', "%{$search}%")
                    ->orWhereHas('courseCatalog', fn ($catalogQuery) => $catalogQuery->where('title', 'like', "%{$search}%"));
            });
        }

        $query->orderBy('sort_order')->orderBy('program_name');

        if ($request->has('page') || $request->has('per_page')) {
            $schedules = $query->paginate($validated['per_page'] ?? 10);

            return $this->sendPaginated(
                CourseScheduleResource::collection($schedules),
                'Course schedules retrieved'
            );
        }

        return $this->sendSuccess(
            CourseScheduleResource::collection($query->get()),
            'Course catalog retrieved'
        );
    }

    public function masterOptions(): JsonResponse
    {
        return app(\App\Http\Controllers\Api\CourseScheduleApiController::class)->masterOptions();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null, true);
        $this->assertProgramAccess($request, $data['division'], $data['course_catalog_id']);
        $data = $this->resolveRelationalData($data);
        $data['course_slug'] = $this->uniqueSlug($data['course_slug'] ?? $data['program_name']);
        $data['registered'] = 0;

        $course = CourseSchedule::create($data);

        return $this->sendSuccess(
            new CourseScheduleResource($course->load(self::RELATIONS)),
            'Course created',
            201
        );
    }

    public function show(Request $request, CourseSchedule $courseSchedule): JsonResponse
    {
        $this->assertScheduleAccess($request, $courseSchedule);
        return $this->sendSuccess(
            new CourseScheduleResource($courseSchedule->load(self::RELATIONS)),
            'Course detail retrieved'
        );
    }

    public function update(Request $request, CourseSchedule $courseSchedule): JsonResponse
    {
        $this->assertScheduleAccess($request, $courseSchedule);
        $data = $this->validated($request, $courseSchedule);
        $this->assertProgramAccess(
            $request,
            $data['division'] ?? $courseSchedule->division,
            $data['course_catalog_id'] ?? $courseSchedule->course_catalog_id
        );
        $data = $this->resolveRelationalData($data);

        if (isset($data['course_slug']) || isset($data['program_name'])) {
            $data['course_slug'] = $this->uniqueSlug(
                $data['course_slug'] ?? $data['program_name'],
                $courseSchedule->id
            );
        }

        unset($data['registered']);
        $courseSchedule->update($data);

        return $this->sendSuccess(
            new CourseScheduleResource($courseSchedule->fresh()->load(self::RELATIONS)),
            'Course updated'
        );
    }

    public function destroy(Request $request, CourseSchedule $courseSchedule): JsonResponse
    {
        $this->assertScheduleAccess($request, $courseSchedule);
        $courseSchedule->update(['is_active' => false, 'is_open' => false]);

        return $this->sendSuccess(null, 'Course archived');
    }

    private function resolveRelationalData(array $data): array
    {
        // 1. Level
        if (!empty($data['course_level_id'])) {
            $lvl = CourseLevel::find($data['course_level_id']);
            if ($lvl) $data['level'] = $lvl->name;
        }

        // 2. Format
        if (!empty($data['learning_format_id'])) {
            $fmt = LearningFormat::find($data['learning_format_id']);
            if ($fmt) $data['format'] = $fmt->code;
        }

        // 3. Location
        if (!empty($data['class_location_id'])) {
            $loc = ClassLocation::find($data['class_location_id']);
            if ($loc) $data['location'] = $loc->name . ($loc->building ? " ({$loc->building})" : '');
        }

        // 4. Day & Time -> schedule_text
        $dayName = !empty($data['schedule_day_id']) ? ScheduleDay::find($data['schedule_day_id'])?->name : null;
        $timeRange = !empty($data['schedule_time_id']) ? ScheduleTime::find($data['schedule_time_id'])?->time_range : null;
        if ($dayName && $timeRange) {
            $data['schedule_text'] = "{$dayName}, {$timeRange}";
        } elseif ($dayName && empty($data['schedule_text'])) {
            $data['schedule_text'] = $dayName;
        } elseif ($timeRange && empty($data['schedule_text'])) {
            $data['schedule_text'] = $timeRange;
        }

        // 5. Term -> dates & batch
        if (!empty($data['term_id'])) {
            $term = Term::find($data['term_id']);
            if ($term) {
                $data['batch'] = $term->name;
                if (empty($data['start_date'])) $data['start_date'] = $term->start_date;
                if (empty($data['end_date'])) $data['end_date'] = $term->end_date;
                if (empty($data['registration_open'])) $data['registration_open'] = $term->registration_open;
                if (empty($data['registration_close'])) $data['registration_close'] = $term->registration_close;
            }
        }

        return $data;
    }

    private function validated(Request $request, ?CourseSchedule $courseSchedule = null, bool $creating = false): array
    {
        $slugRule = 'nullable|string|max:255|unique:course_schedules,course_slug';
        if ($courseSchedule) {
            $slugRule .= ',' . $courseSchedule->id;
        }

        return $request->validate([
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'course_catalog_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:course_catalogs,id'],
            'course_level_id' => ['nullable', 'integer', 'exists:course_levels,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'schedule_day_id' => ['nullable', 'integer', 'exists:schedule_days,id'],
            'schedule_time_id' => ['nullable', 'integer', 'exists:schedule_times,id'],
            'learning_format_id' => ['nullable', 'integer', 'exists:learning_formats,id'],
            'class_location_id' => ['nullable', 'integer', 'exists:class_locations,id'],
            'program_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'course_slug' => $slugRule,
            'course_summary' => ['nullable', 'string'],
            'division' => ['sometimes', 'required', 'string', 'max:50'],
            'batch' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:255'],
            'format' => ['nullable', 'in:luring,daring,hybrid,online,offline'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'schedule_text' => ['nullable', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'fee_display' => ['nullable', 'string', 'max:100'],
            'registration_open' => ['nullable', 'date'],
            'registration_close' => ['nullable', 'date', 'after_or_equal:registration_open'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_open' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function allowedDivisions(Request $request): ?array
    {
        return match ($request->user()?->role) {
            'admin_ppb' => ['ppb', 'ppb_english', 'ppb_foreign', 'testing'],
            'admin_bipa' => ['bipa'],
            'admin_ppp' => ['ppp'],
            default => null,
        };
    }

    private function assertScheduleAccess(Request $request, CourseSchedule $courseSchedule): void
    {
        $divisions = $this->allowedDivisions($request);
        abort_unless($divisions === null || in_array($courseSchedule->division, $divisions, true), 403);
    }

    private function assertProgramAccess(Request $request, string $division, int $catalogId): void
    {
        $divisions = $this->allowedDivisions($request);
        if ($divisions === null) {
            return;
        }

        $catalog = \App\Models\CourseCatalog::findOrFail($catalogId);
        abort_unless(
            in_array($division, $divisions, true) && in_array($catalog->division, $divisions, true),
            403
        );
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'course';
        $slug = $base;
        $suffix = 2;

        while (CourseSchedule::where('course_slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}