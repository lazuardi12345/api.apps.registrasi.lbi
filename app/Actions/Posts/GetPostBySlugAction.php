<?php

namespace App\Actions\Posts;

use App\Models\Post;

class GetPostBySlugAction
{
    /**
     * Fetch a published post by slug.
     */
    public function execute(string $slug): Post
    {
        return Post::published()->with('author')->where('slug', $slug)->firstOrFail();
    }
}
