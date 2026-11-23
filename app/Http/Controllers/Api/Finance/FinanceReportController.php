<?php

namespace App\Http\Controllers\Api\Finance;

use App\Actions\Finance\GetFinanceReportAction;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportController extends BaseApiController
{
    /**
     * GET /api/v1/finance/reports
     *
     * Query params:
     *   division  : ppb | ppb_foreign | ppp | bipa
     *   period    : e.g. 2025-1
     *   status    : verified | Pending | Confirmed
     *   date_from : YYYY-MM-DD
     *   date_to   : YYYY-MM-DD
     *   export    : csv   → download file CSV
     */
    public function index(Request $request, GetFinanceReportAction $action): JsonResponse|StreamedResponse
    {
        $filters = $request->only(['division', 'period', 'status', 'date_from', 'date_to']);

        // Validasi sederhana
        $request->validate([
            'division'  => 'nullable|in:ppb,ppb_foreign,ppp,bipa',
            'status'    => 'nullable|in:verified,Pending,Confirmed',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
        ]);

        // Fase 4B: Export CSV
        if ($request->query('export') === 'csv') {
            return $action->exportCsv(array_filter($filters));
        }

        $report = $action->execute(array_filter($filters));

        return $this->sendSuccess($report, 'Finance report retrieved successfully');
    }
}
