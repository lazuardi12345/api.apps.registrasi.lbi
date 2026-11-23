<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\CourseCatalogResource;
use App\Models\CourseCatalog;
use App\Models\CourseFeeRule;
use App\Models\CourseSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketingCourseCatalogController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $catalogs = CourseCatalog::with('service')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return $this->sendSuccess(CourseCatalogResource::collection($catalogs), 'Course catalogs retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null, true);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title']);

        $catalog = CourseCatalog::create($data);

        return $this->sendSuccess(
            new CourseCatalogResource($catalog->fresh()->load(['service', 'schedules'])),
            'Course catalog created',
            201
        );
    }

    public function show(CourseCatalog $courseCatalog): JsonResponse
    {
        return $this->sendSuccess(
            new CourseCatalogResource($courseCatalog->load(['service', 'schedules'])),
            'Course catalog detail retrieved'
        );
    }

    public function update(Request $request, CourseCatalog $courseCatalog): JsonResponse
    {
        $data = $this->validated($request, $courseCatalog);
        if (isset($data['slug']) || isset($data['title'])) {
            $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['title'], $courseCatalog->id);
        }

        $courseCatalog->update($data);

        return $this->sendSuccess(
            new CourseCatalogResource($courseCatalog->fresh()->load(['service', 'schedules'])),
            'Course catalog updated'
        );
    }

    public function destroy(CourseCatalog $courseCatalog): JsonResponse
    {
        DB::transaction(function () use ($courseCatalog): void {
            $scheduleIds = $courseCatalog->schedules()->pluck('id');

            if ($scheduleIds->isNotEmpty()) {
                CourseFeeRule::whereIn('course_schedule_id', $scheduleIds)->delete();
                CourseSchedule::whereIn('id', $scheduleIds)->delete();
            }

            $courseCatalog->delete();
        });

        return $this->sendSuccess(null, 'Course catalog, levels, schedules, and related tariffs permanently deleted. Enrollment history was preserved.');
    }

    private function validated(Request $request, ?CourseCatalog $catalog = null, bool $creating = false): array
    {
        $slugRule = 'nullable|string|max:255|unique:course_catalogs,slug';
        if ($catalog) $slugRule .= ',' . $catalog->id;

        return $request->validate([
            'legacy_nid' => ['nullable', 'integer'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => $slugRule,
            'summary' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'division' => ['required', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:255'],
            'learning_outcomes' => ['nullable', 'string'],
            'target_audience' => ['nullable', 'string'],
            'instructor_info' => ['nullable', 'string'],
            'duration' => ['nullable', 'string', 'max:255'],
            'registration_url' => ['nullable', 'string', 'max:500'],
            'payment_steps' => ['nullable', 'array'],
            'payment_steps.*.title' => ['required_with:payment_steps', 'string', 'max:255'],
            'payment_steps.*.description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'course';
        $slug = $base;
        $suffix = 2;

        while (CourseCatalog::where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
