<?php

namespace App\Actions\Admin\Enrollment;

use App\Models\CourseEnrollment;

class UpdateEnrollmentStatusAction
{
    /**
     * Update enrollment status, handle postponement or cancellation.
     */
    public function execute(CourseEnrollment $enrollment, string $status, ?string $postponeReason = null): CourseEnrollment
    {
        $payload = ['status' => $status];

        if ($status === 'postponed' && !is_null($postponeReason)) {
            $payload['postpone_reason'] = $postponeReason;
        }

        $enrollment->update($payload);

        return $enrollment->fresh();
    }
}
