<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentConfirmationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legacy_sid' => $this->legacy_sid,
            'enrollment_id' => $this->enrollment_id,
            'enrollment' => new CourseEnrollmentResource($this->whenLoaded('enrollment')),
            'student_name' => $this->student_name,
            'student_email' => $this->student_email,
            'course_name' => $this->course_name,
            'payment_for' => $this->payment_for,
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'account_holder' => $this->account_holder,
            'amount' => (float) $this->amount,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'proof_file' => $this->proof_file,
            'status' => $this->status,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verified_note' => $this->verified_note,
            'verified_by' => $this->verified_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
