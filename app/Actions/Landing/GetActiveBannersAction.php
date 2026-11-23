<?php

namespace App\Actions\Landing;

use App\Models\LandingBanner;
use Illuminate\Database\Eloquent\Collection;

class GetActiveBannersAction
{
    /**
     * Fetch active landing banners, optionally filtered by category.
     */
    public function execute(?string $category = null): Collection
    {
        $query = LandingBanner::active();

        if (!empty($category)) {
            $query->where('category', $category);
        }

        return $query->get();
    }
}
