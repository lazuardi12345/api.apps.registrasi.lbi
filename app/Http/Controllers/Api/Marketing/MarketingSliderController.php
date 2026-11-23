<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Actions\Marketing\Slider\CreateSliderAction;
use App\Actions\Marketing\Slider\DeleteSliderAction;
use App\Actions\Marketing\Slider\UpdateSliderAction;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\LandingSliderResource;
use App\Models\LandingSlider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingSliderController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $sliders = LandingSlider::orderBy('sort_order')->get();

        return $this->sendSuccess(LandingSliderResource::collection($sliders), 'Sliders retrieved');
    }

    public function store(Request $request, CreateSliderAction $action): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image_url' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|max:3072',
            'cta_text' => 'nullable|string|max:100',
            'cta_link' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $slider = $action->execute($validated, $request->file('image_file'));

        return $this->sendSuccess(new LandingSliderResource($slider), 'Slider created successfully', 201);
    }

    public function show(LandingSlider $slider): JsonResponse
    {
        return $this->sendSuccess(new LandingSliderResource($slider), 'Slider detail retrieved');
    }

    public function update(Request $request, LandingSlider $slider, UpdateSliderAction $action): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image_url' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|max:3072',
            'cta_text' => 'nullable|string|max:100',
            'cta_link' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $slider = $action->execute($slider, $validated, $request->file('image_file'));

        return $this->sendSuccess(new LandingSliderResource($slider), 'Slider updated successfully');
    }

    public function destroy(LandingSlider $slider, DeleteSliderAction $action): JsonResponse
    {
        $action->execute($slider);

        return $this->sendSuccess(null, 'Slider deleted successfully');
    }
}
