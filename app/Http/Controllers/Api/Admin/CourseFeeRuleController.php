<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CourseCatalog;
use App\Models\CourseFeeRule;
use App\Models\CourseLevel;
use App\Models\CourseSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class CourseFeeRuleController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $allowedDivisions = $this->allowedDivisions($request);
        $query = CourseFeeRule::with(['courseCatalog', 'courseLevel.courseCatalog', 'term', 'courseSchedule.courseCatalog', 'courseSchedule.courseLevel']);
        if ($allowedDivisions !== null) {
            $query = $this->scopeRulesToDivisions($query, $allowedDivisions);
        }

        return $this->sendSuccess(
            $query->orderBy('fee_type')->orderBy('division')->orderBy('name')->get(),
            'Master tarif berhasil dimuat.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->assertRuleAccess($request, $data);
        $rule = CourseFeeRule::create($data);
        return $this->sendSuccess($rule->load(['courseCatalog', 'courseLevel.courseCatalog', 'term', 'courseSchedule.courseCatalog', 'courseSchedule.courseLevel']), 'Tarif berhasil dibuat.', 201);
    }

    public function update(Request $request, CourseFeeRule $courseFeeRule): JsonResponse
    {
        $this->assertRuleAccess($request, $courseFeeRule->getAttributes());
        $data = $this->validated($request, $courseFeeRule);
        $this->assertRuleAccess($request, array_merge($courseFeeRule->getAttributes(), $data));
        $courseFeeRule->update($data);
        return $this->sendSuccess($courseFeeRule->fresh(['courseCatalog', 'courseLevel.courseCatalog', 'term', 'courseSchedule.courseCatalog', 'courseSchedule.courseLevel']), 'Tarif berhasil diperbarui.');
    }

    public function show(Request $request, CourseFeeRule $courseFeeRule): JsonResponse
    {
        $this->assertRuleAccess($request, $courseFeeRule->getAttributes());
        return $this->sendSuccess(
            $courseFeeRule->load(['courseCatalog', 'courseLevel.courseCatalog', 'term', 'courseSchedule.courseCatalog', 'courseSchedule.courseLevel']),
            'Detail tarif berhasil dimuat.'
        );
    }

    public function destroy(Request $request, CourseFeeRule $courseFeeRule): JsonResponse
    {
        $this->assertRuleAccess($request, $courseFeeRule->getAttributes());
        $courseFeeRule->update(['is_active' => false]);
        return $this->sendSuccess(null, 'Tarif dinonaktifkan.');
    }

    private function validated(Request $request, ?CourseFeeRule $rule = null): array
    {
        return $request->validate([
            'name' => [$rule ? 'sometimes' : 'required', 'string', 'max:191'],
            'fee_type' => [$rule ? 'sometimes' : 'required', 'in:registration,tuition'],
            'division' => ['nullable', 'in:ppb,ppb_english,ppb_foreign,bipa,ppp,testing'],
            'course_catalog_id' => ['nullable', 'integer', 'exists:course_catalogs,id'],
            'course_level_id' => ['nullable', 'integer', 'exists:course_levels,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'course_schedule_id' => ['nullable', 'integer', 'exists:course_schedules,id'],
            'amount' => [$rule ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
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
            $divisions = ['ppb', 'ppb_english', 'ppb_foreign', 'testing'];
        }
        if ($user->hasRole('admin_bipa')) {
            $divisions[] = 'bipa';
        }
        if ($user->hasRole('admin_ppp')) {
            $divisions[] = 'ppp';
        }

        abort_if($divisions === [], 403, 'Anda tidak berwenang mengakses tarif program ini.');

        return array_values(array_unique($divisions));
    }

    private function scopeRulesToDivisions(Builder $query, array $divisions): Builder
    {
        return $query
            ->where(function (Builder $scope) use ($divisions): void {
                $scope->whereIn('division', $divisions)
                    ->orWhereHas('courseCatalog', fn (Builder $catalog) => $catalog->whereIn('division', $divisions))
                    ->orWhereHas('courseLevel.courseCatalog', fn (Builder $catalog) => $catalog->whereIn('division', $divisions))
                    ->orWhereHas('courseSchedule', fn (Builder $schedule) => $schedule->whereIn('division', $divisions));
            })
            ->where(fn (Builder $scope) => $scope->whereNull('division')->orWhereIn('division', $divisions))
            ->whereDoesntHave('courseCatalog', fn (Builder $catalog) => $catalog->whereNotIn('division', $divisions))
            ->whereDoesntHave('courseLevel.courseCatalog', fn (Builder $catalog) => $catalog->whereNotIn('division', $divisions))
            ->whereDoesntHave('courseSchedule', fn (Builder $schedule) => $schedule->whereNotIn('division', $divisions));
    }

    private function assertRuleAccess(Request $request, array $attributes): void
    {
        $allowedDivisions = $this->allowedDivisions($request);
        if ($allowedDivisions === null) {
            return;
        }

        $divisions = array_filter([$attributes['division'] ?? null]);

        if (! empty($attributes['course_catalog_id'])) {
            $divisions[] = CourseCatalog::findOrFail($attributes['course_catalog_id'])->division;
        }

        if (! empty($attributes['course_level_id'])) {
            $level = CourseLevel::with('courseCatalog')->findOrFail($attributes['course_level_id']);
            $divisions[] = $level->courseCatalog?->division;
        }

        if (! empty($attributes['course_schedule_id'])) {
            $divisions[] = CourseSchedule::findOrFail($attributes['course_schedule_id'])->division;
        }

        $divisions = array_values(array_unique(array_filter($divisions)));
        abort_if($divisions === [] || array_diff($divisions, $allowedDivisions), 403, 'Tarif hanya dapat dibuat atau dikelola untuk program Anda.');
    }
}