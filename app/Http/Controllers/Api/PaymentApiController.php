<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payment\CreatePaymentConfirmationAction;
use App\Actions\Payment\GetPaymentStatusAction;
use App\Http\Requests\Payment\StorePaymentConfirmationRequest;
use App\Http\Resources\PaymentConfirmationResource;
use App\Models\CourseEnrollment;
use App\Models\PaymentConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentApiController extends BaseApiController
{
    /**
     * Submit payment confirmation with proof of payment.
     */
    public function confirm(
        StorePaymentConfirmationRequest $request,
        CreatePaymentConfirmationAction $action
    ): JsonResponse {
        $data = $request->safe()->except(['proof_file']);
        if (empty($data['user_id']) && $request->user('api')) {
            $data['user_id'] = $request->user('api')->id;
        }

        $payment = $action->execute(
            $data,
            $request->file('proof_file')
        );

        return $this->sendSuccess(
            new PaymentConfirmationResource($payment),
            'Payment confirmation submitted successfully. Our team will verify it shortly.',
            201
        );
    }

    /**
     * Check payment confirmation status.
     */
    public function show(int $id, GetPaymentStatusAction $action): JsonResponse
    {
        $payment = $action->execute($id);

        return $this->sendSuccess(
            new PaymentConfirmationResource($payment),
            'Payment details retrieved successfully'
        );
    }

    /**
     * Simulate Xendit Payment (Instant Gateway Dummy Simulation).
     */
    public function simulateXendit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollment_id' => 'required',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'nullable|string',
            'student_name' => 'nullable|string',
            'student_email' => 'nullable|email',
        ]);

        $raw = trim((string) $validated['enrollment_id']);
        $enrollment = CourseEnrollment::where('registration_number', $raw)
            ->orWhere('id', is_numeric($raw) ? (int) $raw : 0)
            ->first();

        if (!$enrollment) {
            return $this->sendError('Data pendaftaran tidak ditemukan.', 404);
        }

        $paymentFor = $enrollment->course_schedule_id ? 'tuition' : 'registration';
        $method = strtoupper($validated['payment_method'] ?? 'QRIS');
        $invoiceId = 'XND-INV-' . strtoupper(dechex(time())) . '-' . rand(1000, 9999);

        $payment = PaymentConfirmation::create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'student_name' => $validated['student_name'] ?: $enrollment->student_name,
            'student_email' => $validated['student_email'] ?: $enrollment->student_email,
            'course_name' => $enrollment->course_name,
            'payment_for' => $paymentFor,
            'bank_name' => 'Xendit - ' . $method,
            'account_number' => $invoiceId,
            'account_holder' => $validated['student_name'] ?: $enrollment->student_name,
            'amount' => (float) $validated['amount'],
            'payment_date' => now()->toDateString(),
            'status' => 'verified',
            'verified_at' => now(),
            'verified_note' => 'Pembayaran otomatis terverifikasi via Xendit Gateway Simulation (' . $method . ')',
        ]);

        $nextStatus = match ($paymentFor) {
            'registration' => $enrollment->registration_track === 'placement_test' ? 'awaiting_test_result' : 'awaiting_level',
            'tuition' => 'approved',
            default => 'confirmed',
        };

        $enrollment->update([
            'status' => $nextStatus,
            'payment_status' => 'paid',
        ]);

        return $this->sendSuccess([
            'payment' => new PaymentConfirmationResource($payment),
            'enrollment' => [
                'id' => $enrollment->id,
                'registration_number' => $enrollment->registration_number,
                'status' => $enrollment->fresh()->status,
                'payment_status' => 'paid',
            ],
            'xendit_invoice' => [
                'id' => $invoiceId,
                'status' => 'PAID',
                'payment_method' => $method,
                'paid_at' => now()->toISOString(),
            ]
        ], 'Pembayaran berhasil diproses via Xendit Gateway (Dummy). Status pendaftaran aktif.');
    }

    /**
     * Simulate BNI Virtual Account Payment.
     */
    public function simulateBniVa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollment_id' => 'required',
            'amount' => 'required|numeric|min:1',
            'va_number' => 'nullable|string',
            'student_name' => 'nullable|string',
            'student_email' => 'nullable|email',
        ]);

        $raw = trim((string) $validated['enrollment_id']);
        $enrollment = CourseEnrollment::where('registration_number', $raw)
            ->orWhere('id', is_numeric($raw) ? (int) $raw : 0)
            ->first();

        if (!$enrollment) {
            return $this->sendError('Data pendaftaran tidak ditemukan.', 404);
        }

        $paymentFor = $enrollment->course_schedule_id ? 'tuition' : 'registration';
        $vaNumber = $validated['va_number'] ?? ('988127' . str_pad((string) $enrollment->id, 8, '0', STR_PAD_LEFT));

        $payment = PaymentConfirmation::create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $enrollment->user_id,
            'student_name' => $validated['student_name'] ?: $enrollment->student_name,
            'student_email' => $validated['student_email'] ?: $enrollment->student_email,
            'course_name' => $enrollment->course_name,
            'payment_for' => $paymentFor,
            'bank_name' => 'Bank BNI (Virtual Account)',
            'account_number' => $vaNumber,
            'account_holder' => 'LBI FIB UI - ' . ($validated['student_name'] ?: $enrollment->student_name),
            'amount' => (float) $validated['amount'],
            'payment_date' => now()->toDateString(),
            'status' => 'verified',
            'verified_at' => now(),
            'verified_note' => 'Pembayaran terverifikasi via BNI Virtual Account (' . $vaNumber . ')',
        ]);

        $nextStatus = match ($paymentFor) {
            'registration' => $enrollment->registration_track === 'placement_test' ? 'awaiting_test_result' : 'awaiting_level',
            'tuition' => 'approved',
            default => 'confirmed',
        };

        $enrollment->update([
            'status' => $nextStatus,
            'payment_status' => 'paid',
        ]);

        return $this->sendSuccess([
            'payment' => new PaymentConfirmationResource($payment),
            'enrollment' => [
                'id' => $enrollment->id,
                'registration_number' => $enrollment->registration_number,
                'status' => $enrollment->fresh()->status,
                'payment_status' => 'paid',
            ],
            'va_details' => [
                'bank' => 'BNI',
                'va_number' => $vaNumber,
                'status' => 'PAID',
                'paid_at' => now()->toISOString(),
            ]
        ], 'Pembayaran Virtual Account BNI berhasil disimulasikan. Status pendaftaran aktif.');
    }
}
