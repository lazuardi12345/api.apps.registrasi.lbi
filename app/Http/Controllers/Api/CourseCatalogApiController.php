<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CourseCatalogResource;
use App\Models\CourseCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CourseCatalogApiController extends BaseApiController
{
    public function show(string $slug): JsonResponse
    {
        $normalized = $this->normalizeLegacySlug($slug);

        $catalog = CourseCatalog::query()
            ->where('is_active', true)
            ->where(function ($query) use ($slug, $normalized) {
                $query->where('slug', $slug);

                if ($normalized !== $slug) {
                    $query->orWhere('slug', $normalized);
                    $query->orWhere('slug', 'like', $normalized . '%');
                }

                $legacyVariants = ['daring', 'tatap-muka', 'online', 'offline'];
                foreach ($legacyVariants as $variant) {
                    $query->orWhere('slug', 'like', $normalized . '-' . $variant);
                }
            })
            ->with([
                'service',
                'schedules' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('start_date'),
            ])
            ->orderByRaw('CASE WHEN slug = ? THEN 0 WHEN slug = ? THEN 1 ELSE 2 END', [$slug, $normalized])
            ->firstOrFail();

        return $this->sendSuccess(
            new CourseCatalogResource($catalog),
            'Course catalog detail retrieved successfully'
        );
    }

    protected function normalizeLegacySlug(string $slug): string
    {
        $normalized = preg_replace('/-(?:daring|tatap\s*-?muka|online|offline)$/i', '', $slug);

        return trim((string) ($normalized ?: $slug), '-');
    }
}
