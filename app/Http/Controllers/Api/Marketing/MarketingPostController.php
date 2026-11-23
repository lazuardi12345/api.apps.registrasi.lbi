<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Actions\Marketing\Post\CreatePostAction;
use App\Actions\Marketing\Post\DeletePostAction;
use App\Actions\Marketing\Post\UpdatePostAction;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingPostController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $posts = Post::with('author')->latest()->paginate(15);

        return $this->sendPaginated(PostResource::collection($posts), 'Posts retrieved');
    }

    public function store(Request $request, CreatePostAction $action): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:posts,slug',
            'category' => 'nullable|string|max:100',
            'summary' => 'nullable|string',
            'body' => 'nullable|string',
            'featured_image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|max:4096',
            'is_published' => 'nullable|boolean',
        ]);

        $post = $action->execute($validated, $request->file('image_file'), $request->user()?->id);

        return $this->sendSuccess(new PostResource($post->load('author')), 'Post created successfully', 201);
    }

    public function show(Post $post): JsonResponse
    {
        return $this->sendSuccess(new PostResource($post->load('author')), 'Post detail retrieved');
    }

    public function update(Request $request, Post $post, UpdatePostAction $action): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:posts,slug,' . $post->id,
            'category' => 'nullable|string|max:100',
            'summary' => 'nullable|string',
            'body' => 'nullable|string',
            'featured_image' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|max:4096',
            'is_published' => 'nullable|boolean',
        ]);

        $post = $action->execute($post, $validated, $request->file('image_file'));

        return $this->sendSuccess(new PostResource($post->load('author')), 'Post updated successfully');
    }

    public function destroy(Post $post, DeletePostAction $action): JsonResponse
    {
        $action->execute($post);

        return $this->sendSuccess(null, 'Post deleted successfully');
    }
}
