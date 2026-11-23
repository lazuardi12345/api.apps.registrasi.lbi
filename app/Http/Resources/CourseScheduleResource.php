<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Resolve dates: pakai dari Term jika ada, fallback ke schedule sendiri
        $term = $this->whenLoaded('term') instanceof \App\Models\Term
            ? $this->term
            : null;

        $startDate = $term?->start_date ?? $this->start_date;
        $endDate   = $term?->end_date   ?? $this->end_date;
        $regOpen   = $term?->registration_open  ?? $this->registration_open;
        $regClose  = $term?->registration_close ?? $this->registration_close;
        $batchName = $term?->name ?? $this->batch;

        return [
            'id'                => $this->id,
            'program_name'      => $this->program_name,
            'course_slug'       => $this->course_slug,
            'course_summary'    => $this->course_summary,
            'catalog_only'      => $this->batch === 'Katalog legacy',
            'division'          => $this->division,

            // Batch / Term info
            'batch'             => $batchName,
            'term_id'           => $this->term_id,
            'term'              => $term ? [
                'id'                   => $term->id,
                'name'                 => $term->name,
                'program'              => $term->program,
                'academic_year'        => $term->academic_year,
                'start_date'           => $term->start_date?->toDateString(),
                'end_date'             => $term->end_date?->toDateString(),
                'start_date_display'   => $term->start_date?->translatedFormat('d F Y'),
                'end_date_display'     => $term->end_date?->translatedFormat('d F Y'),
                'registration_open'    => $term->registration_open?->toDateString(),
                'registration_close'   => $term->registration_close?->toDateString(),
                'is_registration_open' => $term->isRegistrationOpen(),
            ] : null,

            'course_catalog_id' => $this->course_catalog_id,
            'course_level_id'   => $this->course_level_id,
            'schedule_day_id'   => $this->schedule_day_id,
            'schedule_time_id'  => $this->schedule_time_id,
            'learning_format_id'=> $this->learning_format_id,
            'class_location_id' => $this->class_location_id,

            'level'             => $this->level,
            'course_level'      => $this->whenLoaded('courseLevel', fn() => [
                'id'         => $this->courseLevel->id,
                'name'       => $this->courseLevel->name,
                'code'       => $this->courseLevel->code,
                'sort_order' => $this->courseLevel->sort_order,
            ]),

            'format'            => $this->format,
            'learning_format'   => $this->whenLoaded('learningFormat', fn() => [
                'id'   => $this->learningFormat->id,
                'code' => $this->learningFormat->code,
                'name' => $this->learningFormat->name,
            ]),

            'schedule_text'     => $this->schedule_text,
            'schedule_day'      => $this->whenLoaded('scheduleDay', fn() => [
                'id'   => $this->scheduleDay->id,
                'name' => $this->scheduleDay->name,
            ]),
            'schedule_time'     => $this->whenLoaded('scheduleTime', fn() => [
                'id'         => $this->scheduleTime->id,
                'time_range' => $this->scheduleTime->time_range,
                'start_time' => $this->scheduleTime->start_time,
                'end_time'   => $this->scheduleTime->end_time,
            ]),

            'location'          => $this->location,
            'class_location'    => $this->whenLoaded('classLocation', fn() => [
                'id'       => $this->classLocation->id,
                'code'     => $this->classLocation->code,
                'name'     => $this->classLocation->name,
                'building' => $this->classLocation->building,
            ]),

            // Dates resolved from term or schedule itself
            'start_date'        => $startDate?->format('Y-m-d'),
            'end_date'          => $endDate?->format('Y-m-d'),
            'start_date_label'  => $startDate?->translatedFormat('d F Y'),
            'end_date_label'    => $endDate?->translatedFormat('d F Y'),

            'capacity'          => $this->capacity,
            'registered'        => $this->registered,
            'slots_available'   => $this->slots_available,
            'is_full'           => $this->is_full,
            'is_open'           => $this->is_open,
            'fee'               => $this->fee,
            'fee_display'       => $this->fee_display,
            'fee_label'         => $this->formatted_fee,
            'registration_open' => $regOpen?->format('Y-m-d'),
            'registration_close'=> $regClose?->format('Y-m-d'),
            'notes'             => $this->notes,

            'service'           => $this->whenLoaded('service', fn() => [
                'id'    => $this->service->id,
                'title' => $this->service->title,
                'slug'  => $this->service->slug,
            ]),
            'course_catalog'    => $this->whenLoaded('courseCatalog', fn() => [
                'id'    => $this->courseCatalog->id,
                'title' => $this->courseCatalog->title,
                'slug'  => $this->courseCatalog->slug,
            ]),
        ];
    }
}
