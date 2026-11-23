<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarketingServiceController extends BaseApiController
{
    public function uploadContentImage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $validated['image']->store('marketing/content', 'public');

        return $this->sendSuccess([
            'url' => '/storage/' . $path,
            'path' => $path,
        ], 'Content image uploaded');
    }

    public function index(): JsonResponse
    {
        return $this->sendSuccess(
            ServiceResource::collection(Service::query()->orderBy('menu_category')->orderBy('menu_order')->orderBy('title')->get()),
            'Services retrieved'
        );
    }

    public function show(Service $service): JsonResponse
    {
        return $this->sendSuccess(new ServiceResource($service), 'Service detail retrieved');
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'video_description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'menu_category' => ['nullable', 'string', 'max:50'],
            'menu_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('video_description', $data) && $data['video_description'] === null) {
            $data['video_description'] = '';
        }

        if ($request->hasFile('image_file')) {
            if ($service->image_url && str_starts_with($service->image_url, '/storage/')) {
                Storage::disk('public')->delete(substr($service->image_url, 9));
            }

            $data['image_url'] = '/storage/' . $request->file('image_file')->store('programs', 'public');
        }
        unset($data['image_file']);
        $service->update($data);

        return $this->sendSuccess(new ServiceResource($service->fresh()), 'Service updated');
    }
}