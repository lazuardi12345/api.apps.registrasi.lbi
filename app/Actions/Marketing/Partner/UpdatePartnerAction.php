<?php

namespace App\Actions\Marketing\Partner;

use App\Models\Partner;
use Illuminate\Http\UploadedFile;

class UpdatePartnerAction
{
    public function execute(Partner $partner, array $data, ?UploadedFile $logoFile = null): Partner
    {
        if ($logoFile) {
            $path = $logoFile->store('partners', 'public');
            $data['logo_url'] = '/storage/' . $path;
        }

        $partner->update($data);

        return $partner->fresh();
    }
}
