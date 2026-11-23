<?php

namespace App\Actions\Marketing\Slider;

use App\Models\LandingSlider;
use Illuminate\Http\UploadedFile;

class CreateSliderAction
{
    public function execute(array $data, ?UploadedFile $imageFile = null): LandingSlider
    {
        if ($imageFile) {
            $path = $imageFile->store('sliders', 'public');
            $data['image_url'] = '/storage/' . $path;
        }

        $data['sort_order'] = $data['sort_order'] ?? ((LandingSlider::max('sort_order') ?? 0) + 1);
        $data['is_active'] = $data['is_active'] ?? true;

        return LandingSlider::create($data);
    }
}
