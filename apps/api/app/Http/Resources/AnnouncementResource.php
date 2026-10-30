<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'retreat_id' => $this->retreat_id,
            'title' => $this->title,
            'content' => $this->content,
            'published_at' => $this->published_at,
            'visible_until' => $this->visible_until,
            'is_published' => $this->isPublished(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
