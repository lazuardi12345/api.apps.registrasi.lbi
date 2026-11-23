<?php

namespace App\Actions\Marketing\Post;

use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class UpdatePostAction
{
    public function execute(Post $post, array $data, ?UploadedFile $featuredImage = null): Post
    {
        if ($featuredImage) {
            $path = $featuredImage->store('posts', 'public');
            $data['featured_image'] = '/storage/' . $path;
        }

        if (isset($data['title']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']) . '-' . Str::random(5);
        }

        $post->update($data);

        return $post->fresh();
    }
}
