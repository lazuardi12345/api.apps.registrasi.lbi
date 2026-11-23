<?php

namespace App\Http\Controllers\Api\Finance;

use App\Actions\Finance\GetPendingPaymentsAction;
use App\Actions\Finance\RejectPaymentAction;
use App\Actions\Finance\VerifyPaymentAction;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\PaymentConfirmationResource;
use App\Models\PaymentConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancePaymentController extends BaseApiController
{
    public function index(Request $request, GetPendingPaymentsAction $action): JsonResponse
    {
        $filters = $request->only(['status', 'bank_name', 'search']);
        $perPage = (int) $request->query('per_page', 15);

        $payments = $action->execute($filters, $perPage);

        return $this->sendPaginated(PaymentConfirmationResource::collection($payments), 'Payment list retrieved');
    }

    public function show(PaymentConfirmation $payment): JsonResponse
    {
        return $this->sendSuccess(
            new PaymentConfirmationResource($payment->load(['enrollment', 'user'])),
            'Payment detail retrieved'
        );
    }

    public function verify(Request $request, PaymentConfirmation $payment, VerifyPaymentAction $action): JsonResponse
    {
        $note = $request->input('note');
        $updatedPayment = $action->execute($payment, $note);

        return $this->sendSuccess(
            new PaymentConfirmationResource($updatedPayment),
            'Payment verified and enrollment confirmed successfully'
        );
    }

    public function reject(Request $request, PaymentConfirmation $payment, RejectPaymentAction $action): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $updatedPayment = $action->execute($payment, $validated['reason']);

        return $this->sendSuccess(
            new PaymentConfirmationResource($updatedPayment),
            'Payment rejected successfully'
        );
    }
}
