<?php

namespace App\Actions\Landing;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Collection;

class GetPartnersAction
{
    /**
     * Fetch all partners for landing page / clients section.
     */
    public function execute(): Collection
    {
        return Partner::all();
    }
}
