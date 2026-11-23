<?php

namespace App\Actions\Marketing\Banner;

use App\Models\LandingBanner;
use Illuminate\Http\UploadedFile;

class UpdateBannerAction
{
    public function execute(LandingBanner $banner, array $data, ?UploadedFile $imageFile = null): LandingBanner
    {
        if ($imageFile) {
            $path = $imageFile->store('banners', 'public');
            $data['image_url'] = '/storage/' . $path;
        }

        $banner->update($data);

        return $banner->fresh();
    }
}
