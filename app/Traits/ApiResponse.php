<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    /**
     * Send a standardized successful JSON response.
     */
    protected function sendSuccess(
        mixed $data = null,
        string $message = 'Operation successful',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Send a standardized error JSON response.
     */
    protected function sendError(
        string $message = 'An error occurred',
        int $statusCode = 400,
        mixed $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Send a standardized paginated JSON response.
     */
    protected function sendPaginated(
        mixed $data,
        string $message = 'Data retrieved successfully',
        int $statusCode = 200
    ): JsonResponse {
        // Handle LengthAwarePaginator or ResourceCollection wrapping a paginator
        $paginator = null;

        if ($data instanceof LengthAwarePaginator) {
            $paginator = $data;
            $items = $paginator->items();
        } elseif ($data instanceof ResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $paginator = $data->resource;
            $items = $data->resolve();
        } else {
            return $this->sendSuccess($data, $message, $statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ], $statusCode);
    }
}
