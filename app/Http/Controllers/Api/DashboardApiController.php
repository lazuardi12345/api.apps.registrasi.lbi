<?php

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\GetDivisionSummaryAction;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends BaseApiController
{
    /**
     * Get aggregate statistics across divisions and recent finance summaries.
     */
    public function statistics(GetDivisionSummaryAction $action): JsonResponse
    {
        $stats = $action->execute();

        return $this->sendSuccess($stats, 'Dashboard statistics retrieved successfully');
    }
}
