<?php

namespace App\Actions\Marketing\Banner;

use App\Models\LandingBanner;
use Illuminate\Http\UploadedFile;

class CreateBannerAction
{
    public function execute(array $data, ?UploadedFile $imageFile = null): LandingBanner
    {
        if ($imageFile) {
            $path = $imageFile->store('banners', 'public');
            $data['image_url'] = '/storage/' . $path;
        }

        $data['is_active'] = $data['is_active'] ?? true;

        return LandingBanner::create($data);
    }
}
