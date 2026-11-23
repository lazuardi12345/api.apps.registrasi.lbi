<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Actions\Marketing\Partner\CreatePartnerAction;
use App\Actions\Marketing\Partner\DeletePartnerAction;
use App\Actions\Marketing\Partner\UpdatePartnerAction;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingPartnerController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $partners = Partner::all();

        return $this->sendSuccess(PartnerResource::collection($partners), 'Partners retrieved');
    }

    public function store(Request $request, CreatePartnerAction $action): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo_url' => 'nullable|string|max:500',
            'logo_file' => 'nullable|image|max:2048',
            'website_url' => 'nullable|string|max:500',
        ]);

        $partner = $action->execute($validated, $request->file('logo_file'));

        return $this->sendSuccess(new PartnerResource($partner), 'Partner created successfully', 201);
    }

    public function show(Partner $partner): JsonResponse
    {
        return $this->sendSuccess(new PartnerResource($partner), 'Partner detail retrieved');
    }

    public function update(Request $request, Partner $partner, UpdatePartnerAction $action): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'logo_url' => 'nullable|string|max:500',
            'logo_file' => 'nullable|image|max:2048',
            'website_url' => 'nullable|string|max:500',
        ]);

        $partner = $action->execute($partner, $validated, $request->file('logo_file'));

        return $this->sendSuccess(new PartnerResource($partner), 'Partner updated successfully');
    }

    public function destroy(Partner $partner, DeletePartnerAction $action): JsonResponse
    {
        $action->execute($partner);

        return $this->sendSuccess(null, 'Partner deleted successfully');
    }
}
