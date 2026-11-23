<?php

namespace App\Actions\Finance;

use App\Models\PaymentConfirmation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetPendingPaymentsAction
{
    /**
     * Get paginated pending payments for verification.
     */
    public function execute(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PaymentConfirmation::with(['enrollment', 'user'])->latest();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', 'pending');
        }

        if (!empty($filters['bank_name'])) {
            $query->where('bank_name', $filters['bank_name']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                  ->orWhere('student_email', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }
}
