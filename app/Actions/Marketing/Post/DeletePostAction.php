<?php

namespace App\Actions\Marketing\Post;

use App\Models\Post;

class DeletePostAction
{
    public function execute(Post $post): bool
    {
        return $post->delete();
    }
}
