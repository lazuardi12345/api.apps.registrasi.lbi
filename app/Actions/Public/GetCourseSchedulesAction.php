<?php

namespace App\Actions\Public;

use App\Models\CourseSchedule;
use Illuminate\Support\Collection;

class GetCourseSchedulesAction
{
    /**
     * Get public course schedules.
     *
     * @param  string|null  $division  Filter: bipa | ppb_english | ppp
     * @param  bool         $openOnly  Only show schedules open for registration
     * @return Collection<CourseSchedule>
     */
    public function execute(?string $division = null, bool $openOnly = false, ?int $serviceId = null, ?int $termId = null): Collection
    {
        $query = CourseSchedule::active()
            ->with(['service', 'courseCatalog', 'term'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('start_date', 'asc');

        if ($division) {
            $divisions = str_starts_with($division, 'ppb')
                ? ['ppb', 'ppb_english', 'ppb_foreign']
                : [$division];
            $query->whereIn('division', $divisions);
        }

        if ($serviceId) {
            $query->where('service_id', $serviceId);
        }

        if ($termId) {
            $query->where('term_id', $termId);
        }

        if ($openOnly) {
            $query->open();
        }

        return $query->get();
    }
}
