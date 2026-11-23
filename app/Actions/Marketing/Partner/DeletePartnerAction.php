<?php

namespace App\Actions\Marketing\Partner;

use App\Models\Partner;

class DeletePartnerAction
{
    public function execute(Partner $partner): bool
    {
        return $partner->delete();
    }
}
