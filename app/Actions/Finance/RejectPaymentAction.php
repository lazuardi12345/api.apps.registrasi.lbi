<?php

namespace App\Actions\Finance;

use App\Models\CourseEnrollment;
use App\Models\PaymentConfirmation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RejectPaymentAction
{
    /**
     * Reject a payment confirmation.
     */
    public function execute(PaymentConfirmation $payment, string $reason): PaymentConfirmation
    {
        return DB::transaction(function () use ($payment, $reason) {
            $payment->update([
                'status'        => 'rejected',
                'verified_note' => $reason,
                'verified_by'   => Auth::id(),
            ]);

            if ($payment->enrollment_id) {
                CourseEnrollment::where('id', $payment->enrollment_id)->update([
                    'status' => 'payment_rejected',
                ]);
            }

            return $payment->fresh(['enrollment', 'user']);
        });
    }
}
