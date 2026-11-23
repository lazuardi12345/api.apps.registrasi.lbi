<?php

namespace App\Actions\Marketing\Partner;

use App\Models\Partner;
use Illuminate\Http\UploadedFile;

class CreatePartnerAction
{
    public function execute(array $data, ?UploadedFile $logoFile = null): Partner
    {
        if ($logoFile) {
            $path = $logoFile->store('partners', 'public');
            $data['logo_url'] = '/storage/' . $path;
        }

        return Partner::create($data);
    }
}
