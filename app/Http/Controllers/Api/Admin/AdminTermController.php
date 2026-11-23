<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Term;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin Term Management Controller
 * Handles CRUD for academic term master data per program (PPB, PPP, BIPA).
 */
class AdminTermController extends BaseApiController
{
    use ApiResponse;

    /**
     * GET /api/v1/admin/terms
     * List all terms, filterable by program.
     */
    public function index(Request $request): JsonResponse
    {
        $program = $request->query('program');
        $allowedPrograms = $this->allowedPrograms($request);

        $terms = Term::query()
            ->when($allowedPrograms !== null, fn ($q) => $q->whereIn('program', $allowedPrograms))
            ->when($program && $program !== 'all', fn($q) => $q->where('program', $program))
            ->orderByDesc('is_current')
            ->orderBy('sort_order')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn(Term $t) => $this->formatTerm($t));

        return $this->sendSuccess($terms, 'Terms retrieved');
    }

    /**
     * GET /api/v1/public/terms  (also used by admin)
     * Public endpoint to get terms for a given program (for registration form).
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $program = $request->query('program', 'all');

        $terms = Term::active()
            ->where(function ($q) use ($program) {
                $q->where('program', $program)->orWhere('program', 'all');
            })
            ->orderByDesc('is_current')
            ->orderBy('sort_order')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn(Term $t) => $this->formatTerm($t));

        return $this->sendSuccess($terms, 'Active terms retrieved');
    }

    /**
     * POST /api/v1/admin/terms
     */
    public function store(Request $request): JsonResponse
    {
        $allowedPrograms = $this->allowedPrograms($request);
        $data = $request->validate([
            'name'                => 'required|string|max:100',
            'program'             => 'required|in:ppb,ppp,bipa,all',
            'description'         => 'nullable|string|max:255',
            'academic_year'       => 'nullable|string|max:20',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'registration_open'   => 'nullable|date',
            'registration_close'  => 'nullable|date|after_or_equal:registration_open',
            'is_active'           => 'nullable|boolean',
            'is_current'          => 'nullable|boolean',
            'sort_order'          => 'nullable|integer|min:0',
        ]);
        $this->assertProgramAccess($allowedPrograms, $data['program']);

        if (!empty($data['is_current'])) {
            $currentTerms = Term::where('program', $data['program']);
            if ($allowedPrograms === null) {
                $currentTerms->orWhere('program', 'all');
            }
            $currentTerms->update(['is_current' => false]);
        }

        $term = Term::create($data);

        return $this->sendSuccess($this->formatTerm($term), 'Term created', 201);
    }

    /**
     * GET /api/v1/admin/terms/{term}
     */
    public function show(Request $request, Term $term): JsonResponse
    {
        $this->assertTermAccess($request, $term);
        return $this->sendSuccess($this->formatTerm($term), 'Term detail');
    }

    /**
     * PUT /api/v1/admin/terms/{term}
     */
    public function update(Request $request, Term $term): JsonResponse
    {
        $allowedPrograms = $this->allowedPrograms($request);
        $this->assertTermAccess($request, $term, $allowedPrograms);
        $data = $request->validate([
            'name'                => 'sometimes|required|string|max:100',
            'program'             => 'sometimes|required|in:ppb,ppp,bipa,all',
            'description'         => 'nullable|string|max:255',
            'academic_year'       => 'nullable|string|max:20',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'registration_open'   => 'nullable|date',
            'registration_close'  => 'nullable|date|after_or_equal:registration_open',
            'is_active'           => 'nullable|boolean',
            'is_current'          => 'nullable|boolean',
            'sort_order'          => 'nullable|integer|min:0',
        ]);

        $prog = $data['program'] ?? $term->program;
        $this->assertProgramAccess($allowedPrograms, $prog);

        if (!empty($data['is_current'])) {
            $currentTerms = Term::where('id', '!=', $term->id)
                ->where('program', $prog);
            if ($allowedPrograms === null) {
                $currentTerms->orWhere(function ($q) use ($prog, $term) {
                    $q->where('id', '!=', $term->id)->where('program', 'all');
                });
            }
            $currentTerms->update(['is_current' => false]);
        }

        $term->update($data);

        return $this->sendSuccess($this->formatTerm($term->fresh()), 'Term updated');
    }

    /**
     * DELETE /api/v1/admin/terms/{term}
     */
    public function destroy(Request $request, Term $term): JsonResponse
    {
        $this->assertTermAccess($request, $term);
        // Soft-delete: cukup non-aktifkan, jangan hapus karena schedules mungkin masih referensi
        $term->update(['is_active' => false, 'is_current' => false]);

        return $this->sendSuccess(null, 'Term deactivated');
    }

    // ─── Private Helpers ───────────────────────────────────────────────────

    private function allowedPrograms(Request $request): ?array
    {
        $user = $request->user('api');
        abort_unless($user, 401);

        if ($user->hasRole('administrator')) {
            return null;
        }

        $programs = [];
        if ($user->hasRole('admin_ppb')) {
            $programs[] = 'ppb';
        }
        if ($user->hasRole('admin_ppp')) {
            $programs[] = 'ppp';
        }
        if ($user->hasRole('admin_bipa')) {
            $programs[] = 'bipa';
        }

        abort_if($programs === [], 403, 'Anda tidak berwenang mengakses term program ini.');

        return array_values(array_unique($programs));
    }

    private function assertProgramAccess(?array $allowedPrograms, string $program): void
    {
        abort_if(
            $allowedPrograms !== null && ! in_array($program, $allowedPrograms, true),
            403,
            'Term hanya dapat dibuat atau dikelola untuk program Anda.'
        );
    }

    private function assertTermAccess(Request $request, Term $term, ?array $allowedPrograms = null): void
    {
        $allowedPrograms ??= $this->allowedPrograms($request);
        abort_if(
            $allowedPrograms !== null && ! in_array($term->program, $allowedPrograms, true),
            403,
            'Term hanya dapat dikelola untuk program Anda.'
        );
    }

    private function formatTerm(Term $t): array
    {
        return [
            'id'                  => $t->id,
            'name'                => $t->name,
            'program'             => $t->program,
            'program_label'       => match($t->program) {
                'ppb'  => 'PPB (English & Bahasa Asing)',
                'ppp'  => 'PPP (Penerjemahan & Penjurubahasaan)',
                'bipa' => 'BIPA',
                default => 'Semua Program',
            },
            'description'         => $t->description,
            'academic_year'       => $t->academic_year,
            'start_date'          => $t->start_date?->toDateString(),
            'end_date'            => $t->end_date?->toDateString(),
            'start_date_display'  => $t->start_date?->translatedFormat('d F Y'),
            'end_date_display'    => $t->end_date?->translatedFormat('d F Y'),
            'registration_open'   => $t->registration_open?->toDateString(),
            'registration_close'  => $t->registration_close?->toDateString(),
            'reg_open_display'    => $t->registration_open?->translatedFormat('d F Y'),
            'reg_close_display'   => $t->registration_close?->translatedFormat('d F Y'),
            'is_active'           => $t->is_active,
            'is_current'          => $t->is_current,
            'is_registration_open'=> $t->isRegistrationOpen(),
            'sort_order'          => $t->sort_order,
            'schedules_count'     => $t->schedules()->count(),
        ];
    }
}
