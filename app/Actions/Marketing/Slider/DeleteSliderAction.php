<?php

namespace App\Actions\Marketing\Slider;

use App\Models\LandingSlider;

class DeleteSliderAction
{
    public function execute(LandingSlider $slider): bool
    {
        return $slider->delete();
    }
}
