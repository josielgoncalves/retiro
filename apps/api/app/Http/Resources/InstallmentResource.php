<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'installment_number' => $this->installment_number,
            'amount_cents' => $this->amount_cents,
            'due_date' => $this->due_date?->toDateString(),
            'paid_cents' => $this->paidCents(),
            'balance_cents' => $this->balanceCents(),
            'status' => $this->status(),
        ];
    }
}
