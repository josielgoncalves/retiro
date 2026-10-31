<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RetreatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'location' => $this->location,
            'registration_open' => $this->registration_open,
            'status' => $this->status,
            'cover_image_url' => $this->cover_image_path ? route('retreats.cover', $this->id, false) : null,
            'pix_key_type' => $this->pix_key_type,
            'pix_key' => $this->pix_key,
            'pix_beneficiary_name' => $this->pix_beneficiary_name,
            'receipt_whatsapp' => $this->receipt_whatsapp,
            'enrollments_count' => $this->when(isset($this->enrollments_count), $this->enrollments_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
