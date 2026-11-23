<?php

namespace App\Actions\Programs;

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetServicesAction
{
    /**
     * Fetch active services with optional search and pagination.
     */
    public function execute(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Service::active();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }
}
