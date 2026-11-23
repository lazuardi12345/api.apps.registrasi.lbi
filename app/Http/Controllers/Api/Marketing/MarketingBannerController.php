<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Actions\Marketing\Banner\CreateBannerAction;
use App\Actions\Marketing\Banner\DeleteBannerAction;
use App\Actions\Marketing\Banner\UpdateBannerAction;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\LandingBannerResource;
use App\Models\LandingBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingBannerController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $banners = LandingBanner::latest()->get();

        return $this->sendSuccess(LandingBannerResource::collection($banners), 'Banners retrieved');
    }

    public function store(Request $request, CreateBannerAction $action): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|max:3072',
            'link_url' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $banner = $action->execute($validated, $request->file('image_file'));

        return $this->sendSuccess(new LandingBannerResource($banner), 'Banner created successfully', 201);
    }

    public function show(LandingBanner $banner): JsonResponse
    {
        return $this->sendSuccess(new LandingBannerResource($banner), 'Banner detail retrieved');
    }

    public function update(Request $request, LandingBanner $banner, UpdateBannerAction $action): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|max:3072',
            'link_url' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $banner = $action->execute($banner, $validated, $request->file('image_file'));

        return $this->sendSuccess(new LandingBannerResource($banner), 'Banner updated successfully');
    }

    public function destroy(LandingBanner $banner, DeleteBannerAction $action): JsonResponse
    {
        $action->execute($banner);

        return $this->sendSuccess(null, 'Banner deleted successfully');
    }
}
