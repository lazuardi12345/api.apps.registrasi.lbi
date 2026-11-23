<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'summary' => $this->summary ?: (strip_tags($this->body ?? '') ? mb_substr(strip_tags($this->body), 0, 160) . '...' : null),
            'video_description' => $this->video_description,
            'icon' => $this->icon,
            'image_url' => $this->image_url,
            'video_url' => $this->video_url,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
