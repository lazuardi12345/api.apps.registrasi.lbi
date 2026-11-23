<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legacy_nid' => $this->legacy_nid,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary ?: $this->title,
            'description' => $this->description,
            'division' => $this->division,
            'category' => $this->category,
            'level' => $this->level,
            'learning_outcomes' => $this->learning_outcomes,
            'target_audience' => $this->target_audience,
            'instructor_info' => $this->instructor_info,
            'duration' => $this->duration,
            'registration_url' => $this->registration_url,
            'payment_steps' => $this->payment_steps ?: [],
            'image_url' => $this->image_url,
            'is_active' => (bool) $this->is_active,
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service->id,
                'title' => $this->service->title,
                'slug' => $this->service->slug,
            ]),
            'schedules' => CourseScheduleResource::collection($this->whenLoaded('schedules')),
        ];
    }
}
