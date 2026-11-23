<?php

namespace App\Actions\Fees;

use App\Models\CourseFeeRule;

class ResolveCourseFeeAction
{
    public function execute(array $filters): ?CourseFeeRule
    {
        $query = CourseFeeRule::query()
            ->effective()
            ->where('fee_type', $filters['fee_type']);

        foreach (['division', 'course_catalog_id', 'course_level_id', 'course_schedule_id', 'term_id'] as $key) {
            if (array_key_exists($key, $filters) && $filters[$key] !== null && $filters[$key] !== '') {
                $query->where(fn ($builder) => $builder->whereNull($key)->orWhere($key, $filters[$key]));
            } else {
                $query->whereNull($key);
            }
        }

        $rules = $query->get();
        $specificity = ['course_schedule_id', 'course_level_id', 'course_catalog_id', 'term_id', 'division'];

        return $rules->sortByDesc(function (CourseFeeRule $rule) use ($specificity): int {
            $score = 0;
            foreach ($specificity as $index => $key) {
                if ($rule->{$key} !== null) {
                    $score += (count($specificity) - $index) * 10;
                }
            }
            return $score;
        })->first();
    }
}