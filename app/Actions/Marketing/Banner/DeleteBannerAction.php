<?php

namespace App\Actions\Marketing\Banner;

use App\Models\LandingBanner;

class DeleteBannerAction
{
    public function execute(LandingBanner $banner): bool
    {
        return $banner->delete();
    }
}
