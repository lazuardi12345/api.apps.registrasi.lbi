<?php

namespace App\Actions\Dashboard;

use Illuminate\Support\Facades\DB;

class GetDivisionSummaryAction
{
    /**
     * Retrieve aggregate summary statistics from database view.
     */
    public function execute(): array
    {
        $summary = DB::table('v_division_summary')->get();
        $finance = DB::table('v_finance_dashboard')->limit(12)->get();

        return [
            'division_summary' => $summary,
            'recent_finance' => $finance,
        ];
    }
}
