<?php

namespace App\Actions\Finance;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GetFinanceReportAction
{
    /**
     * Fase 4A - Finance report dengan filter lengkap.
     *
     * @param array{
     *   division?:  string,
     *   period?:    string,   // e.g. "2025-1"
     *   status?:    string,   // verified|Pending|Confirmed
     *   date_from?: string,   // Y-m-d
     *   date_to?:   string,   // Y-m-d
     * } $filters
     */
    public function execute(array $filters = []): array
    {
        // ---------- Division Summary ----------
        $divQuery = DB::table('v_division_summary');
        if (! empty($filters['division'])) {
            $divQuery->where('division', $filters['division']);
        }
        $divisionSummary = $divQuery->get();

        // ---------- Monthly Breakdown ----------
        $monthQuery = DB::table('v_finance_dashboard');
        if (! empty($filters['division'])) {
            $monthQuery->where('division', $filters['division']);
        }
        $monthlyBreakdown = $monthQuery->limit(24)->get();

        // ---------- Payment Confirmations base query ----------
        $paymentsQuery = DB::table('payment_confirmations as pc')
            ->leftJoin('course_enrollments as ce', 'pc.enrollment_id', '=', 'ce.id');

        if (! empty($filters['division'])) {
            $paymentsQuery->where('ce.division', $filters['division']);
        }
        if (! empty($filters['period'])) {
            $paymentsQuery->where('ce.term_period', $filters['period']);
        }
        if (! empty($filters['status'])) {
            $paymentsQuery->where('pc.status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $paymentsQuery->whereDate('pc.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $paymentsQuery->whereDate('pc.created_at', '<=', $filters['date_to']);
        }

        // Clone for total counts
        $baseQuery = clone $paymentsQuery;

        $totalRevenue  = (clone $paymentsQuery)->where('pc.status', 'verified')->sum('pc.amount');
        $pendingRevenue = (clone $paymentsQuery)->where('pc.status', 'Pending')->sum('pc.amount');

        // ---------- Per-division breakdown (filtered) ----------
        $perDivision = (clone $baseQuery)
            ->select('ce.division', DB::raw("SUM(CASE WHEN pc.status='verified' THEN pc.amount ELSE 0 END) as verified_amount"),
                     DB::raw("COUNT(CASE WHEN pc.status='verified' THEN 1 END) as verified_count"),
                     DB::raw("SUM(CASE WHEN pc.status='Pending' THEN pc.amount ELSE 0 END) as pending_amount"),
                     DB::raw("COUNT(CASE WHEN pc.status='Pending' THEN 1 END) as pending_count"))
            ->groupBy('ce.division')
            ->orderBy('ce.division')
            ->get();

        return [
            'filters'                => $filters,
            'total_confirmed_revenue'=> (float) $totalRevenue,
            'total_pending_revenue'  => (float) $pendingRevenue,
            'per_division'           => $perDivision,
            'division_summary'       => $divisionSummary,
            'monthly_breakdown'      => $monthlyBreakdown,
        ];
    }

    /**
     * Fase 4B - Export CSV pembayaran sesuai filter.
     */
    public function exportCsv(array $filters = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 agar Excel tidak kacau
            fwrite($handle, "\xEF\xBB\xBF");

            // Header CSV
            fputcsv($handle, [
                'ID', 'No. Pendaftaran', 'Nama Siswa', 'Email', 'Division',
                'Kursus', 'Level', 'Jumlah (Rp)', 'Status Pembayaran',
                'Tanggal Upload', 'Tanggal Verifikasi', 'Catatan Verifikasi',
            ]);

            // Query data
            $query = DB::table('payment_confirmations as pc')
                ->leftJoin('course_enrollments as ce', 'pc.enrollment_id', '=', 'ce.id')
                ->select([
                    'pc.id', 'ce.registration_number', 'pc.student_name', 'pc.student_email',
                    'ce.division', 'pc.course_name', 'ce.level_name',
                    'pc.amount', 'pc.status', 'pc.created_at', 'pc.verified_at', 'pc.verified_note',
                ]);

            if (! empty($filters['division'])) {
                $query->where('ce.division', $filters['division']);
            }
            if (! empty($filters['period'])) {
                $query->where('ce.term_period', $filters['period']);
            }
            if (! empty($filters['status'])) {
                $query->where('pc.status', $filters['status']);
            }
            if (! empty($filters['date_from'])) {
                $query->whereDate('pc.created_at', '>=', $filters['date_from']);
            }
            if (! empty($filters['date_to'])) {
                $query->whereDate('pc.created_at', '<=', $filters['date_to']);
            }

            $query->orderBy('pc.created_at', 'desc')
                  ->chunk(500, function ($rows) use ($handle) {
                      foreach ($rows as $row) {
                          fputcsv($handle, [
                              $row->id,
                              $row->registration_number,
                              $row->student_name,
                              $row->student_email,
                              strtoupper($row->division ?? ''),
                              $row->course_name,
                              $row->level_name,
                              number_format((float) $row->amount, 0, ',', '.'),
                              $row->status,
                              $row->created_at,
                              $row->verified_at,
                              $row->verified_note,
                          ]);
                      }
                  });

            fclose($handle);
        }, 'laporan-keuangan-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
