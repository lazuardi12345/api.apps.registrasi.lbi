<?php

namespace App\Actions\Finance;

use App\Models\CourseEnrollment;
use App\Models\PaymentConfirmation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VerifyPaymentAction
{
    /**
     * Verify a payment confirmation and mark corresponding enrollment as confirmed/paid.
     *
     * @param  PaymentConfirmation  $payment
     * @param  string|null          $note    Optional note from the finance officer
     */
    public function execute(PaymentConfirmation $payment, ?string $note = null): PaymentConfirmation
    {
        return DB::transaction(function () use ($payment, $note) {
            $payment->update([
                'status'        => 'verified',
                'verified_at'   => now(),
                'verified_note' => $note,
                'verified_by'   => Auth::id(),
            ]);

            if ($payment->enrollment_id) {
                $enrollment = CourseEnrollment::find($payment->enrollment_id);
                $nextEnrollmentStatus = match ($payment->payment_for) {
                    'registration' => $enrollment?->registration_track === 'placement_test' ? 'awaiting_test_result' : 'awaiting_level',
                    'tuition' => 'approved',
                    default => 'confirmed',
                };
                $enrollment?->update([
                    'status'         => $nextEnrollmentStatus,
                    'payment_status' => 'paid',
                ]);
            }

            return $payment->fresh(['enrollment', 'user']);
        });
    }
}
