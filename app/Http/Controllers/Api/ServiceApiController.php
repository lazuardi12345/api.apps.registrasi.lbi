<?php

namespace App\Http\Controllers\Api;

use App\Actions\Programs\GetServiceBySlugAction;
use App\Actions\Programs\GetServicesAction;
use App\Http\Resources\ServiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceApiController extends BaseApiController
{
    /**
     * List all programs/services.
     */
    public function index(Request $request, GetServicesAction $action): JsonResponse
    {
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 15);

        $services = $action->execute($search, $perPage);

        return $this->sendPaginated(
            ServiceResource::collection($services),
            'Services retrieved successfully'
        );
    }

    /**
     * Get single service detail by slug.
     */
    public function show(string $slug, GetServiceBySlugAction $action): JsonResponse
    {
        $service = $action->execute($slug);

        return $this->sendSuccess(
            new ServiceResource($service),
            'Service detail retrieved successfully'
        );
    }

    /**
     * Get structured navigation menu categories of services for Navbar.
     */
    public function navMenu(\App\Actions\Programs\GetServicesNavMenuAction $action): JsonResponse
    {
        $menu = $action->execute();

        return $this->sendSuccess($menu, 'Services nav menu retrieved successfully');
    }
}
