<?php

namespace App\Actions\Posts;

use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetPostsAction
{
    /**
     * Fetch published posts with optional category filter, search, and pagination.
     */
    public function execute(?string $category = null, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = Post::published()->with('author');

        if (!empty($category)) {
            $query->where('category', $category);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }
}
