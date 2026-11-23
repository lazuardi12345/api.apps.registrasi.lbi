<?php

namespace App\Actions\Payment;

use App\Models\PaymentConfirmation;

class GetPaymentStatusAction
{
    /**
     * Get payment confirmation detail by ID or legacy SID.
     */
    public function execute(int $id): PaymentConfirmation
    {
        return PaymentConfirmation::with(['enrollment', 'user'])->findOrFail($id);
    }
}
