<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['retreat_id', 'couple_id', 'payment_mode', 'total_amount_cents', 'status', 'registered_at'])]
class Enrollment extends Model
{
    use HasFactory;

    public const MODE_FIXED = 'fixed';

    public const MODE_FLEXIBLE = 'flexible';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'total_amount_cents' => 'integer',
            'registered_at' => 'datetime',
        ];
    }

    public function retreat(): BelongsTo
    {
        return $this->belongsTo(Retreat::class);
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('installment_number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function activePayments(): HasMany
    {
        return $this->payments()->whereNull('reversed_at');
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(PortalAccessToken::class);
    }

    public function isFixed(): bool
    {
        return $this->payment_mode === self::MODE_FIXED;
    }

    public function isFlexible(): bool
    {
        return $this->payment_mode === self::MODE_FLEXIBLE;
    }

    public function totalPaidCents(): int
    {
        return (int) $this->activePayments()->sum('amount_cents');
    }

    public function balanceCents(): int
    {
        return max(0, $this->total_amount_cents - $this->totalPaidCents());
    }

    public function percentPaid(): float
    {
        if ($this->total_amount_cents <= 0) {
            return 0.0;
        }

        return round(($this->totalPaidCents() / $this->total_amount_cents) * 100, 2);
    }

    public function nextDueInstallment(): ?Installment
    {
        return $this->installments
            ->filter(fn (Installment $installment) => $installment->balanceCents() > 0)
            ->sortBy([['due_date', 'asc'], ['installment_number', 'asc']])
            ->first();
    }
}
