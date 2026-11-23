<?php

namespace App\Actions\Enrollment;

use App\Models\CourseEnrollment;
use Illuminate\Support\Facades\DB;

class ContinueEnrollmentAction
{
    /**
     * Lanjutkan enrollment yang sebelumnya ditunda (postponed).
     *
     * Aturan bisnis:
     *  - Hanya enrollment berstatus 'postponed' yang bisa dilanjutkan
    *  - Status berubah ke 'pending_payment' jika tuition belum dibayar
    *  - Jika pembayaran tuition sudah terverifikasi, status → 'approved'
     *  - postpone_reason dihapus
     */
    public function execute(CourseEnrollment $enrollment): CourseEnrollment
    {
        if ($enrollment->status !== 'postponed') {
            throw new \InvalidArgumentException(
                "Hanya enrollment berstatus 'postponed' yang dapat dilanjutkan. " .
                "Status saat ini: '{$enrollment->status}'."
            );
        }

        DB::transaction(function () use ($enrollment) {
            $hasVerifiedTuition = $enrollment->course_schedule_id
                && $enrollment->payments()
                ->where('payment_for', 'tuition')
                ->where('status', 'verified')
                ->exists();

            $newStatus = $hasVerifiedTuition ? 'approved' : 'pending_payment';

            $enrollment->update([
                'status'          => $newStatus,
                'postpone_reason' => null,
            ]);
        });

        return $enrollment->fresh(['payments']);
    }
}
