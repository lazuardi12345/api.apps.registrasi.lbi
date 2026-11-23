<?php

namespace App\Http\Controllers\Api;

use App\Actions\Posts\GetPostBySlugAction;
use App\Actions\Posts\GetPostsAction;
use App\Http\Resources\PostResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostApiController extends BaseApiController
{
    /**
     * List posts/articles/blogs with category filter and search.
     */
    public function index(Request $request, GetPostsAction $action): JsonResponse
    {
        $category = $request->query('category');
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 10);

        $posts = $action->execute($category, $search, $perPage);

        return $this->sendPaginated(
            PostResource::collection($posts),
            'Posts retrieved successfully'
        );
    }

    /**
     * Get single post detail by slug.
     */
    public function show(string $slug, GetPostBySlugAction $action): JsonResponse
    {
        $post = $action->execute($slug);

        return $this->sendSuccess(
            new PostResource($post),
            'Post detail retrieved successfully'
        );
    }
}
