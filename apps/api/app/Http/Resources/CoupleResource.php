<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoupleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partner_one_name' => $this->partner_one_name,
            'partner_two_name' => $this->partner_two_name,
            'phone' => $this->phone,
            'secondary_phone' => $this->secondary_phone,
            'email' => $this->email,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
