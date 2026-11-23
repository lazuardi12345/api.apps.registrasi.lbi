<?php

namespace App\Actions\Enrollment;

use App\Models\CourseEnrollment;
use Illuminate\Support\Facades\DB;

class PostponeEnrollmentAction
{
    /**
     * Tunda (postpone) enrollment siswa.
     *
     * Aturan bisnis:
    *  - Hanya enrollment dengan status kursus yang memenuhi syarat yang bisa ditunda
     *  - Status berubah ke 'postponed'
     *  - Alasan wajib diisi
     */
    public function execute(CourseEnrollment $enrollment, string $reason): CourseEnrollment
    {
        $allowedStatuses = ['approved', 'confirmed', 'pending_payment', 'paid'];

        if (! in_array($enrollment->status, $allowedStatuses)) {
            throw new \InvalidArgumentException(
                "Enrollment dengan status '{$enrollment->status}' tidak dapat ditunda. " .
                "Hanya status [" . implode(', ', $allowedStatuses) . "] yang bisa ditunda."
            );
        }

        DB::transaction(function () use ($enrollment, $reason) {
            $enrollment->update([
                'status'         => 'postponed',
                'postpone_reason'=> $reason,
            ]);
        });

        return $enrollment->fresh();
    }
}
