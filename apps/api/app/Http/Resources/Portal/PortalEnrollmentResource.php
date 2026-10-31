<?php

namespace App\Http\Resources\Portal;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a couple is allowed to see about their own enrollment. No internal
 * database IDs, administrative notes, audit history, or data belonging to
 * other couples/admin users ever goes into this payload.
 */
class PortalEnrollmentResource extends JsonResource
{
    /** @var Enrollment */
    public $resource;

    public function toArray(Request $request): array
    {
        $nextDue = $this->nextDueInstallment();

        return [
            'couple' => [
                'partner_one_name' => $this->couple->partner_one_name,
                'partner_two_name' => $this->couple->partner_two_name,
            ],
            'retreat' => [
                'name' => $this->retreat->name,
                'start_date' => $this->retreat->start_date?->toDateString(),
                'end_date' => $this->retreat->end_date?->toDateString(),
                'location' => $this->retreat->location,
                'cover_image_url' => $this->retreat->cover_image_path
                    ? route('retreats.cover', $this->retreat->id, false)
                    : null,
                'pix_key_type' => $this->retreat->pix_key_type,
                'pix_key' => $this->retreat->pix_key,
                'pix_beneficiary_name' => $this->retreat->pix_beneficiary_name,
                'receipt_whatsapp' => $this->retreat->receipt_whatsapp,
            ],
            'payment_mode' => $this->payment_mode,
            'total_amount_cents' => $this->total_amount_cents,
            'total_paid_cents' => $this->totalPaidCents(),
            'balance_cents' => $this->balanceCents(),
            'percent_paid' => $this->isFlexible() ? $this->percentPaid() : null,
            'next_due_installment' => ($this->isFixed() && $nextDue) ? [
                'installment_number' => $nextDue->installment_number,
                'due_date' => $nextDue->due_date->toDateString(),
                'balance_cents' => $nextDue->balanceCents(),
            ] : null,
            'installments' => $this->isFixed()
                ? $this->installments->map(fn ($installment) => [
                    'installment_number' => $installment->installment_number,
                    'amount_cents' => $installment->amount_cents,
                    'due_date' => $installment->due_date->toDateString(),
                    'status' => $installment->status(),
                ])->values()
                : null,
            'payments' => $this->activePayments->sortByDesc('paid_at')->map(fn ($payment) => [
                'amount_cents' => $payment->amount_cents,
                'paid_at' => $payment->paid_at,
                'payment_method' => $payment->payment_method,
            ])->values(),
        ];
    }
}
