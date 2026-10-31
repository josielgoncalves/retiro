<?php

namespace App\Services;

use App\Models\Couple;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Retreat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Centralizes every financial calculation and invariant for enrollments,
 * installments, payments and allocations. Controllers must never compute
 * totals/balances themselves nor trust values coming from the frontend.
 */
class FinancialService
{
    /**
     * Split a total amount into $count installments whose sum is exactly
     * equal to the total, distributing the rounding remainder (in cents)
     * deterministically across the first installments.
     *
     * @return array<int, int> amount in cents per installment, in order
     */
    public function distributeInstallmentAmounts(int $totalCents, int $count): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('O número de parcelas deve ser pelo menos 1.');
        }

        $base = intdiv($totalCents, $count);
        $remainder = $totalCents % $count;

        $amounts = [];

        for ($i = 0; $i < $count; $i++) {
            $amounts[] = $base + ($i < $remainder ? 1 : 0);
        }

        return $amounts;
    }

    /** @return array<int, array{installment_number:int,due_date:string,amount_cents:int}> */
    public function previewInstallments(int $totalCents, int $count, string $firstDueDate, string $periodicity): array
    {
        if ($totalCents < 1 || $count < 1 || $count > 120 || $totalCents < $count) {
            throw ValidationException::withMessages([
                'installment_count' => ['A quantidade deve permitir parcelas de pelo menos um centavo.'],
            ]);
        }

        $amounts = $this->distributeInstallmentAmounts($totalCents, $count);
        $first = CarbonImmutable::parse($firstDueDate)->startOfDay();
        $originalDay = $first->day;

        return array_map(function (int $index) use ($amounts, $first, $originalDay, $periodicity) {
            if ($periodicity === 'weekly') {
                $dueDate = $first->addWeeks($index);
            } else {
                $month = $first->startOfMonth()->addMonths($index);
                $dueDate = $month->day(min($originalDay, $month->daysInMonth));
            }

            return [
                'installment_number' => $index + 1,
                'due_date' => $dueDate->toDateString(),
                'amount_cents' => $amounts[$index],
            ];
        }, range(0, $count - 1));
    }

    /**
     * Create an enrollment, generating installments for fixed-mode payments.
     *
     * @param  array{
     *     payment_mode: string,
     *     total_amount_cents: int,
     *     registered_at?: \DateTimeInterface|string,
     *     installments?: array<int, array{due_date: \DateTimeInterface|string}>,
     * }  $data
     */
    public function createEnrollment(Retreat $retreat, Couple $couple, array $data): Enrollment
    {
        if ($data['total_amount_cents'] <= 0) {
            throw ValidationException::withMessages([
                'total_amount_cents' => ['O valor contratado deve ser maior que zero.'],
            ]);
        }

        if ($data['payment_mode'] === Enrollment::MODE_FIXED && empty($data['installments'])) {
            throw ValidationException::withMessages([
                'installments' => ['Informe ao menos uma parcela para a modalidade fixa.'],
            ]);
        }

        return DB::transaction(function () use ($retreat, $couple, $data) {
            $enrollment = Enrollment::create([
                'retreat_id' => $retreat->id,
                'couple_id' => $couple->id,
                'payment_mode' => $data['payment_mode'],
                'total_amount_cents' => $data['total_amount_cents'],
                'status' => Enrollment::STATUS_ACTIVE,
                'registered_at' => $data['registered_at'] ?? now(),
            ]);

            if ($data['payment_mode'] === Enrollment::MODE_FIXED) {
                $dueDates = array_column($data['installments'], 'due_date');
                $providedAmounts = array_column($data['installments'], 'amount_cents');
                $hasCustomAmounts = count($providedAmounts) === count($dueDates);
                $amounts = $hasCustomAmounts
                    ? array_map('intval', $providedAmounts)
                    : $this->distributeInstallmentAmounts($data['total_amount_cents'], count($dueDates));

                if (array_sum($amounts) !== (int) $data['total_amount_cents']) {
                    throw ValidationException::withMessages([
                        'installments' => ['A soma das parcelas deve ser exatamente igual ao valor contratado.'],
                    ]);
                }

                if (collect($amounts)->contains(fn (int $amount) => $amount <= 0)) {
                    throw ValidationException::withMessages([
                        'installments' => ['Todas as parcelas devem possuir valor maior que zero.'],
                    ]);
                }

                foreach ($dueDates as $index => $dueDate) {
                    Installment::create([
                        'enrollment_id' => $enrollment->id,
                        'installment_number' => $index + 1,
                        'amount_cents' => $amounts[$index],
                        'due_date' => $dueDate,
                    ]);
                }
            }

            return $enrollment;
        });
    }

    public function updateInstallment(Installment $installment, array $data, ?User $user = null): Installment
    {
        return DB::transaction(function () use ($installment, $data, $user) {
            $target = Installment::whereKey($installment->id)->lockForUpdate()->firstOrFail();
            $enrollment = Enrollment::whereKey($target->enrollment_id)->lockForUpdate()->firstOrFail();
            $target->load('allocations.payment');
            $before = $target->toArray();
            $newAmount = (int) ($data['amount_cents'] ?? $target->amount_cents);

            if ($newAmount !== $target->amount_cents) {
                if ($target->status() === Installment::STATUS_PAID) {
                    throw ValidationException::withMessages(['amount_cents' => ['O valor de uma parcela quitada não pode ser alterado.']]);
                }
                if ($newAmount < $target->paidCents()) {
                    throw ValidationException::withMessages(['amount_cents' => ['O valor não pode ser inferior ao total já recebido nesta parcela.']]);
                }

                $compensatingId = (int) ($data['compensating_installment_id'] ?? 0);
                $compensating = Installment::where('enrollment_id', $enrollment->id)
                    ->whereKey($compensatingId)->whereKeyNot($target->id)->lockForUpdate()->first();
                if (! $compensating) {
                    throw ValidationException::withMessages(['compensating_installment_id' => ['Selecione outra parcela desta inscrição para compensar a diferença.']]);
                }
                $compensating->load('allocations.payment');
                if ($compensating->status() === Installment::STATUS_PAID) {
                    throw ValidationException::withMessages(['compensating_installment_id' => ['A parcela de compensação não pode estar quitada.']]);
                }

                $difference = $newAmount - $target->amount_cents;
                $compensatingAmount = $compensating->amount_cents - $difference;
                if ($compensatingAmount <= 0 || $compensatingAmount < $compensating->paidCents()) {
                    throw ValidationException::withMessages(['compensating_installment_id' => ['A compensação deixaria a outra parcela inválida ou abaixo do valor recebido.']]);
                }
                $compensatingBefore = $compensating->toArray();
                $compensating->update(['amount_cents' => $compensatingAmount]);
                app(AuditLogger::class)->log('installment.compensated', $compensating, $compensatingBefore, $compensating->fresh()->toArray(), $user);
            }

            $target->update(['due_date' => $data['due_date'], 'amount_cents' => $newAmount]);
            app(AuditLogger::class)->log('installment.updated', $target, $before, $target->fresh()->toArray(), $user);

            return $target->fresh()->load('allocations.payment');
        });
    }

    /** @return array<int, array{id:int,installment_number:int,old_due_date:string,new_due_date:string}> */
    public function previewPendingDueDates(Enrollment $enrollment, string $firstDueDate, string $periodicity): array
    {
        $pending = $enrollment->installments()->with('allocations.payment')->get()
            ->filter(fn (Installment $installment) => $installment->status() !== Installment::STATUS_PAID)
            ->values();
        $dates = $this->previewInstallments(max(1, $pending->count()), max(1, $pending->count()), $firstDueDate, $periodicity);

        return $pending->map(fn (Installment $installment, int $index) => [
            'id' => $installment->id,
            'installment_number' => $installment->installment_number,
            'old_due_date' => $installment->due_date->toDateString(),
            'new_due_date' => $dates[$index]['due_date'],
        ])->all();
    }

    public function regeneratePendingDueDates(Enrollment $enrollment, string $firstDueDate, string $periodicity, ?User $user = null): Collection
    {
        return DB::transaction(function () use ($enrollment, $firstDueDate, $periodicity, $user) {
            Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            $changes = $this->previewPendingDueDates($enrollment, $firstDueDate, $periodicity);
            $updated = collect();
            foreach ($changes as $change) {
                $installment = Installment::where('enrollment_id', $enrollment->id)->whereKey($change['id'])->lockForUpdate()->firstOrFail();
                $before = $installment->toArray();
                $installment->update(['due_date' => $change['new_due_date']]);
                app(AuditLogger::class)->log('installment.due_dates_regenerated', $installment, $before, $installment->fresh()->toArray(), $user);
                $updated->push($installment->fresh()->load('allocations.payment'));
            }

            return $updated;
        });
    }

    /**
     * Block changes to payment mode or contracted amount once the
     * enrollment already has active (non-reversed) payment history.
     */
    public function assertFinancialsAreEditable(Enrollment $enrollment, array $incoming): void
    {
        $hasHistory = $enrollment->activePayments()->exists();

        if (! $hasHistory) {
            return;
        }

        $changingMode = array_key_exists('payment_mode', $incoming) && $incoming['payment_mode'] !== $enrollment->payment_mode;
        $changingTotal = array_key_exists('total_amount_cents', $incoming) && (int) $incoming['total_amount_cents'] !== $enrollment->total_amount_cents;

        if ($changingMode || $changingTotal) {
            throw ValidationException::withMessages([
                'total_amount_cents' => ['Não é possível alterar a modalidade ou o valor contratado de uma inscrição que já possui pagamentos. É necessário um processo de revisão financeira (fora do escopo deste MVP).'],
            ]);
        }
    }

    /**
     * Register a payment against an enrollment, allocating it to
     * installments (fixed mode) or recording it directly (flexible mode).
     *
     * @param  array{
     *     amount_cents: int,
     *     paid_at: \DateTimeInterface|string,
     *     payment_method: string,
     *     reference?: string|null,
     *     notes?: string|null,
     *     installment_ids?: array<int, int>,
     * }  $data
     */
    public function registerPayment(Enrollment $enrollment, array $data, ?User $user): Payment
    {
        $amountCents = (int) $data['amount_cents'];

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'amount_cents' => ['O valor do pagamento deve ser maior que zero.'],
            ]);
        }

        return DB::transaction(function () use ($enrollment, $data, $user, $amountCents) {
            // Lock the enrollment row so concurrent payment registrations for
            // the same enrollment are serialized, preventing the balance
            // check below from racing with another in-flight payment.
            /** @var Enrollment $lockedEnrollment */
            $lockedEnrollment = Enrollment::where('id', $enrollment->id)->lockForUpdate()->first();

            $balance = $lockedEnrollment->balanceCents();

            if ($amountCents > $balance) {
                throw ValidationException::withMessages([
                    'amount_cents' => ['O valor informado (R$ '.number_format($amountCents / 100, 2, ',', '.').') excede o saldo restante da inscrição (R$ '.number_format($balance / 100, 2, ',', '.').')'],
                ]);
            }

            $payment = Payment::create([
                'enrollment_id' => $lockedEnrollment->id,
                'amount_cents' => $amountCents,
                'paid_at' => $data['paid_at'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            if ($lockedEnrollment->isFixed()) {
                $this->allocatePayment($lockedEnrollment, $payment, $data['installment_ids'] ?? null);
            } elseif (! empty($data['installment_ids'])) {
                throw ValidationException::withMessages([
                    'installment_ids' => ['Inscrições na modalidade livre não possuem parcelas para alocação.'],
                ]);
            }

            return $payment;
        });
    }

    /**
     * Allocate a fixed-mode payment to installments: to explicitly chosen
     * installments (in the given order) when provided, otherwise
     * automatically to the oldest unpaid installment first.
     *
     * @param  array<int, int>|null  $installmentIds
     */
    protected function allocatePayment(Enrollment $enrollment, Payment $payment, ?array $installmentIds): void
    {
        $installments = Installment::where('enrollment_id', $enrollment->id)
            ->lockForUpdate()
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get()
            ->load('allocations.payment');

        if ($installmentIds) {
            $installmentIds = array_values(array_unique($installmentIds));

            $ordered = collect($installmentIds)
                ->map(fn ($id) => $installments->firstWhere('id', $id))
                ->filter();

            if ($ordered->count() !== count($installmentIds)) {
                throw ValidationException::withMessages([
                    'installment_ids' => ['Uma ou mais parcelas informadas não pertencem a esta inscrição.'],
                ]);
            }

            $targets = $ordered;
        } else {
            $targets = $installments->filter(fn (Installment $i) => $i->balanceCents() > 0);
        }

        $remaining = $payment->amount_cents;

        foreach ($targets as $installment) {
            if ($remaining <= 0) {
                break;
            }

            $installmentBalance = $installment->balanceCents();

            if ($installmentBalance <= 0) {
                continue;
            }

            $allocationAmount = min($remaining, $installmentBalance);

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'installment_id' => $installment->id,
                'amount_cents' => $allocationAmount,
            ]);

            $remaining -= $allocationAmount;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'amount_cents' => ['O pagamento não pôde ser totalmente alocado às parcelas selecionadas. Saldo não alocado: R$ '.number_format($remaining / 100, 2, ',', '.')],
            ]);
        }
    }

    public function reversePayment(Payment $payment, string $reason, ?User $user): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $user) {
            /** @var Payment $locked */
            $locked = Payment::where('id', $payment->id)->lockForUpdate()->first();

            if ($locked->isReversed()) {
                throw ValidationException::withMessages([
                    'payment' => ['Este pagamento já foi estornado anteriormente.'],
                ]);
            }

            // reversed_at/reversed_by/reversal_reason are intentionally left
            // out of Payment's fillable list so clients can never set them
            // through normal mass assignment; forceFill is the trusted,
            // service-only path for actually performing a reversal.
            $locked->forceFill([
                'reversed_at' => now(),
                'reversed_by' => $user?->id,
                'reversal_reason' => $reason,
            ])->save();

            return $locked->refresh();
        });
    }
}
