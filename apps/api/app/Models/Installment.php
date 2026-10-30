<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['enrollment_id', 'installment_number', 'amount_cents', 'due_date'])]
class Installment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'due_date' => 'date',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function activeAllocations(): HasMany
    {
        return $this->allocations()->whereHas('payment', function ($query) {
            $query->whereNull('reversed_at');
        });
    }

    public function paidCents(): int
    {
        if ($this->relationLoaded('allocations')) {
            return (int) $this->allocations
                ->filter(fn (PaymentAllocation $allocation) => $allocation->payment && $allocation->payment->reversed_at === null)
                ->sum('amount_cents');
        }

        return (int) $this->activeAllocations()->sum('amount_cents');
    }

    public function balanceCents(): int
    {
        return max(0, $this->amount_cents - $this->paidCents());
    }

    public function status(): string
    {
        $balance = $this->balanceCents();

        if ($balance <= 0) {
            return self::STATUS_PAID;
        }

        if ($balance < $this->amount_cents && $this->paidCents() > 0) {
            return $this->due_date->isPast() ? self::STATUS_OVERDUE : self::STATUS_PARTIAL;
        }

        return $this->due_date->isPast() ? self::STATUS_OVERDUE : self::STATUS_PENDING;
    }
}
