<?php

namespace App\Actions\Enrollment;

use App\Models\CourseEnrollment;
use Illuminate\Support\Facades\DB;

class TransferEnrollmentAction
{
    /**
     * Transfer siswa ke program (division) lain.
     *
     * Aturan bisnis:
     *  - Hanya enrollment berstatus 'approved' atau 'postponed' yang bisa ditransfer
     *  - Division tujuan harus valid
     *  - Enrollment lama ditandai 'transferred'
     *  - Dibuat enrollment BARU untuk division tujuan (mewarisi data siswa + biaya)
     */
    private const VALID_DIVISIONS = ['ppb', 'ppb_foreign', 'ppp', 'bipa'];

    public function execute(
        CourseEnrollment $enrollment,
        string $targetDivision,
        ?string $reason = null
    ): CourseEnrollment {
        $targetDivision = strtolower($targetDivision);

        if (! in_array($targetDivision, self::VALID_DIVISIONS)) {
            throw new \InvalidArgumentException(
                "Division tujuan '{$targetDivision}' tidak valid. " .
                "Pilihan: " . implode(', ', self::VALID_DIVISIONS)
            );
        }

        if ($targetDivision === $enrollment->division) {
            throw new \InvalidArgumentException(
                "Division tujuan sama dengan division saat ini ('{$enrollment->division}')."
            );
        }

        $allowedStatuses = ['approved', 'postponed'];
        if (! in_array($enrollment->status, $allowedStatuses)) {
            throw new \InvalidArgumentException(
                "Hanya enrollment berstatus [" . implode(', ', $allowedStatuses) . "] yang dapat ditransfer."
            );
        }

        $newEnrollment = DB::transaction(function () use ($enrollment, $targetDivision, $reason) {
            // Tandai enrollment lama sebagai transferred
            $enrollment->update([
                'status'          => 'transferred',
                'postpone_reason' => $reason ?? "Transfer ke program {$targetDivision}",
            ]);

            // Buat enrollment baru untuk program tujuan
            return CourseEnrollment::create([
                'user_id'              => $enrollment->user_id,
                'student_name'         => $enrollment->student_name,
                'student_email'        => $enrollment->student_email,
                'student_phone'        => $enrollment->student_phone,
                'division'             => $targetDivision,
                'student_type'         => $enrollment->student_type,
                'term_period'          => $enrollment->term_period,
                'campus_location'      => $enrollment->campus_location,
                'learning_mode'        => $enrollment->learning_mode,
                'registration_source'  => 'transfer',
                'registration_fee'     => $enrollment->registration_fee,
                'discount_percent'     => $enrollment->discount_percent,
                'status'               => 'pending_payment',
                'submitted_at'         => now(),
                'metadata'             => array_merge($enrollment->metadata ?? [], [
                    'transferred_from'   => $enrollment->id,
                    'transfer_reason'    => $reason,
                    'transferred_at'     => now()->toIso8601String(),
                ]),
            ]);
        });

        return $newEnrollment->fresh();
    }
}
