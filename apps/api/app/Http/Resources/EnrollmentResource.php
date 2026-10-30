<?php

namespace App\Http\Resources;

use App\Models\Installment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nextDue = $this->resource->relationLoaded('installments') ? $this->nextDueInstallment() : null;

        return [
            'id' => $this->id,
            'retreat' => new RetreatResource($this->whenLoaded('retreat')),
            'couple' => new CoupleResource($this->whenLoaded('couple')),
            'payment_mode' => $this->payment_mode,
            'total_amount_cents' => $this->total_amount_cents,
            'status' => $this->status,
            'registered_at' => $this->registered_at,
            'total_paid_cents' => $this->totalPaidCents(),
            'balance_cents' => $this->balanceCents(),
            'percent_paid' => $this->percentPaid(),
            'next_due_installment' => $nextDue ? [
                'id' => $nextDue->id,
                'installment_number' => $nextDue->installment_number,
                'due_date' => $nextDue->due_date->toDateString(),
                'balance_cents' => $nextDue->balanceCents(),
            ] : null,
            'installments_paid_count' => $this->resource->relationLoaded('installments')
                ? $this->installments->filter(fn (Installment $i) => $i->status() === Installment::STATUS_PAID)->count()
                : null,
            'installments_count' => $this->resource->relationLoaded('installments') ? $this->installments->count() : null,
            'installments' => InstallmentResource::collection($this->whenLoaded('installments')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
