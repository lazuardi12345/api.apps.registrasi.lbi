<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PlacementTestToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPlacementTestTokenController extends BaseApiController
{
    /**
     * List all placement test tokens with filters and summary stats.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'all'); // 'all', 'used', 'unused'
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 25);

        $query = PlacementTestToken::query();

        if ($status === 'used') {
            $query->used();
        } elseif ($status === 'unused') {
            $query->available();
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('token_code', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%")
                  ->orWhere('student_name', 'like', "%{$search}%")
                  ->orWhere('student_email', 'like', "%{$search}%")
                  ->orWhere('student_phone', 'like', "%{$search}%");
            });
        }

        $totalCount = PlacementTestToken::count();
        $usedCount = PlacementTestToken::used()->count();
        $unusedCount = PlacementTestToken::available()->count();

        $paginator = $query->orderBy('is_used', 'desc')
            ->orderBy('id', 'asc')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(function ($token) {
            return $token->toResponseArray();
        });

        return response()->json([
            'success' => true,
            'message' => 'Placement test tokens retrieved successfully',
            'data' => $items,
            'stats' => [
                'total' => $totalCount,
                'used' => $usedCount,
                'unused' => $unusedCount,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        ]);
    }

    /**
     * Mark email sent / re-send placement test token email.
     */
    public function sendEmail(Request $request, PlacementTestToken $token): JsonResponse
    {
        if (! $token->is_used || empty($token->student_email)) {
            return $this->sendError('Token ini belum terpasang ke peserta pendaftar.', 422);
        }

        $token->update([
            'email_sent_at' => now(),
        ]);

        return $this->sendSuccess([
            'token' => $token->toResponseArray(),
        ], "Email instruksi dan token tes berhasil dikirim ke {$token->student_email}");
    }
}
