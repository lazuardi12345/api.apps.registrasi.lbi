<?php

namespace App\Http\Controllers\Api;

use App\Actions\Landing\GetActiveBannersAction;
use App\Actions\Landing\GetActiveSlidersAction;
use App\Actions\Landing\GetLandingOverviewAction;
use App\Actions\Landing\GetPartnersAction;
use App\Http\Resources\LandingBannerResource;
use App\Http\Resources\LandingSliderResource;
use App\Http\Resources\PartnerResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\ServiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandingApiController extends BaseApiController
{
    /**
     * Get complete overview data for the landing page in one call.
     */
    public function overview(GetLandingOverviewAction $action): JsonResponse
    {
        $overview = $action->execute();

        return $this->sendSuccess([
            'sliders' => LandingSliderResource::collection($overview['sliders']),
            'banners' => LandingBannerResource::collection($overview['banners']),
            'partners' => PartnerResource::collection($overview['partners']),
            'services' => ServiceResource::collection($overview['services']),
            'latest_posts' => PostResource::collection($overview['latest_posts']),
        ], 'Landing page overview retrieved successfully');
    }

    /**
     * Get active hero sliders.
     */
    public function sliders(GetActiveSlidersAction $action): JsonResponse
    {
        $sliders = $action->execute();

        return $this->sendSuccess(
            LandingSliderResource::collection($sliders),
            'Sliders retrieved successfully'
        );
    }

    /**
     * Get active promotional banners.
     */
    public function banners(Request $request, GetActiveBannersAction $action): JsonResponse
    {
        $banners = $action->execute($request->query('category'));

        return $this->sendSuccess(
            LandingBannerResource::collection($banners),
            'Banners retrieved successfully'
        );
    }

    /**
     * Get partner logos.
     */
    public function partners(GetPartnersAction $action): JsonResponse
    {
        $partners = $action->execute();

        return $this->sendSuccess(
            PartnerResource::collection($partners),
            'Partners retrieved successfully'
        );
    }

}
