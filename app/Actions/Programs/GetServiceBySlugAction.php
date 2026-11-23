<?php

namespace App\Actions\Programs;

use App\Models\Service;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetServiceBySlugAction
{
    /**
     * Standard division aliases
     */
    private const ALIASES = [
        'bipa' => 'program-bipa-129',
        'ppb'  => 'program-pelayanan-bahasa-127',
        'ppp'  => 'program-penerjemahan-dan-penjurubahasaan-1283',
    ];

    /**
     * Fetch a single active service by its slug or alias.
     */
    public function execute(string $slug): Service
    {
        $normalized = strtolower(trim($slug));
        $targetSlug = self::ALIASES[$normalized] ?? $normalized;

        // 1. Exact match
        $service = Service::active()->where('slug', $targetSlug)->first();

        // 2. Exact match on normalized slug
        if (!$service && $targetSlug !== $normalized) {
            $service = Service::active()->where('slug', $normalized)->first();
        }

        // 3. Fallback partial match (e.g. 'bipa-reguler')
        if (!$service) {
            $service = Service::active()
                ->where('slug', 'like', "%{$targetSlug}%")
                ->orWhere('slug', 'like', "%{$normalized}%")
                ->first();
        }

        if (!$service) {
            throw new ModelNotFoundException("Service with slug '{$slug}' not found.");
        }

        return $service;
    }
}
