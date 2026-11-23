<?php

namespace App\Actions\Landing;

use App\Models\LandingBanner;
use App\Models\LandingSlider;
use App\Models\Partner;
use App\Models\Service;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;

class GetLandingOverviewAction
{
    /**
     * Consolidate all landing page data for high-speed client consumption.
     */
    public function execute(): array
    {
        return Cache::remember('landing:overview', 300, function () {
            $sliders = LandingSlider::active()->get();
            $banners = LandingBanner::active()->limit(6)->get();
            $partners = Partner::all();
            $services = Service::active()
                ->whereIn('slug', [
                    'program-bipa-129',
                    'program-pelayanan-bahasa-127',
                    'program-penerjemahan-dan-penjurubahasaan-1283',
                ])
                ->orderBy('menu_order')
                ->get();
            $latestPosts = Post::published()->limit(4)->get();

            return [
                'sliders' => $sliders,
                'banners' => $banners,
                'partners' => $partners,
                'services' => $services,
                'latest_posts' => $latestPosts,
            ];
        });
    }
}
