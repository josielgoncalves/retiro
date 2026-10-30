<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_cents' => $this->amount_cents,
            'paid_at' => $this->paid_at,
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'reversed_at' => $this->reversed_at,
            'reversed_by' => $this->whenLoaded('reversedBy', fn () => $this->reversedBy?->name),
            'reversal_reason' => $this->reversal_reason,
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'installment_id' => $allocation->installment_id,
                'installment_number' => $allocation->installment?->installment_number,
                'amount_cents' => $allocation->amount_cents,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
