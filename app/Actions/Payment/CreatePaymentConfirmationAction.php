<?php

namespace App\Actions\Payment;

use App\Models\CourseEnrollment;
use App\Models\PaymentConfirmation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreatePaymentConfirmationAction
{
    /**
     * Store payment confirmation and optionally handle uploaded proof file.
     */
    public function execute(array $data, ?UploadedFile $proofFile = null): PaymentConfirmation
    {
        return DB::transaction(function () use ($data, $proofFile) {
            if ($proofFile) {
                $path = $proofFile->store('payments', 'public');
                $data['proof_file'] = '/storage/' . $path;
            }

            $data['status'] = $data['status'] ?? 'pending';

            $payment = PaymentConfirmation::create($data);

            // If linked to an enrollment, update enrollment status
            if (!empty($data['enrollment_id'])) {
                $enrollment = CourseEnrollment::find($data['enrollment_id']);
                if ($enrollment && in_array($enrollment->status, ['pending', 'placement_test', 'awaiting_level', 'awaiting_test_result', 'pending_payment'], true)) {
                    $enrollment->update(['status' => 'waiting_verification']);
                }
            }

            return $payment;
        });
    }
}
