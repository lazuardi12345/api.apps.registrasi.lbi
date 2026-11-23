<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LandingSliderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'image_url' => $this->image_url,
            'cta_text' => $this->cta_text,
            'cta_link' => $this->cta_link,
            'sort_order' => $this->sort_order,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
