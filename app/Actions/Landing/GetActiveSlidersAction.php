<?php

namespace App\Actions\Landing;

use App\Models\LandingSlider;
use Illuminate\Database\Eloquent\Collection;

class GetActiveSlidersAction
{
    /**
     * Fetch all active sliders sorted by order.
     */
    public function execute(): Collection
    {
        return LandingSlider::active()->get();
    }
}
