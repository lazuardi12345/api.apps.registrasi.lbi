<?php

namespace App\Actions\Marketing\Post;

use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CreatePostAction
{
    public function execute(array $data, ?UploadedFile $featuredImage = null, ?int $authorId = null): Post
    {
        if ($featuredImage) {
            $path = $featuredImage->store('posts', 'public');
            $data['featured_image'] = '/storage/' . $path;
        }

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']) . '-' . Str::random(5);
        }

        if ($authorId) {
            $data['author_id'] = $authorId;
        }

        $data['is_published'] = $data['is_published'] ?? true;

        return Post::create($data);
    }
}
