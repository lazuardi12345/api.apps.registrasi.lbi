<?php

namespace App\Actions\Marketing\Slider;

use App\Models\LandingSlider;
use Illuminate\Http\UploadedFile;

class UpdateSliderAction
{
    public function execute(LandingSlider $slider, array $data, ?UploadedFile $imageFile = null): LandingSlider
    {
        if ($imageFile) {
            $path = $imageFile->store('sliders', 'public');
            $data['image_url'] = '/storage/' . $path;
        }

        $slider->update($data);

        return $slider->fresh();
    }
}
