<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseEnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'registration_number' => $this->registration_number,
            'legacy_sid'          => $this->legacy_sid,
            'webform_id'          => $this->webform_id,
            'course_catalog_id'   => $this->course_catalog_id,
            'course_level_id'     => $this->course_level_id,
            'course_schedule_id'  => $this->course_schedule_id,
            'term_id'             => $this->term_id,
            'term'                => $this->whenLoaded('term'),
            'participant_profile' => $this->whenLoaded('user', function ($user) {
                $profile = $user?->profile;
                if (!$profile) return null;

                return [
                    'birth_place' => $profile->birth_place,
                    'birth_date' => $profile->birth_date?->format('Y-m-d'),
                    'gender' => $profile->gender,
                    'education' => $profile->education,
                    'occupation' => $profile->occupation,
                    'address' => $profile->address,
                    'identity_number' => $profile->identity_number,
                ];
            }),
            'metadata'            => $this->metadata,
            'division'            => $this->division,
            'student_type'        => $this->student_type,
            'registration_track'  => $this->registration_track,
            'term_period'         => $this->term_period,
            'student_name'        => $this->student_name,
            'student_email'       => $this->student_email,
            'student_phone'       => $this->student_phone,
            'course_name'         => $this->course_name,
            'level_name'          => $this->level_name,
            'campus_location'     => $this->campus_location,
            'learning_mode'       => $this->learning_mode,
            'schedule_days'       => $this->schedule_days,
            'schedule_time'       => $this->schedule_time,
            'discount_percent'    => (float) $this->discount_percent,
            'registration_fee'    => (float) $this->registration_fee,
            'tuition_fee'         => (float) $this->tuition_fee,
            'course_fee_rule_id'  => $this->course_fee_rule_id,
            'status'              => $this->status,
            'completion_status'   => $this->completion_status,
            'payment_status'      => $this->payment_status,
            'postpone_reason'     => $this->postpone_reason,
            'sar_document_path'   => $this->sar_document_path,
            'certificate_path'    => $this->certificate_path,
            'payments'            => PaymentConfirmationResource::collection($this->whenLoaded('payments')),
            'latest_payment'      => $this->whenLoaded('latestPayment', fn () => new PaymentConfirmationResource($this->latestPayment)),
            'submitted_at'        => $this->submitted_at?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
