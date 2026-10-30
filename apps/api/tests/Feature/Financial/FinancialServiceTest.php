<?php

namespace Tests\Feature\Financial;

use App\Models\Couple;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Retreat;
use App\Models\User;
use App\Services\FinancialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FinancialService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FinancialService::class);
    }

    // --- Distribuição de parcelas (arredondamento) -------------------------

    public function test_installment_amounts_sum_exactly_to_total_with_no_remainder(): void
    {
        $amounts = $this->service->distributeInstallmentAmounts(120000, 4);

        $this->assertSame([30000, 30000, 30000, 30000], $amounts);
        $this->assertSame(120000, array_sum($amounts));
    }

    public function test_installment_amounts_distribute_rounding_remainder_deterministically(): void
    {
        // 100000 / 3 = 33333.33... -> remainder of 1 cent distributed to the
        // first installment, deterministically.
        $amounts = $this->service->distributeInstallmentAmounts(100000, 3);

        $this->assertSame([33334, 33333, 33333], $amounts);
        $this->assertSame(100000, array_sum($amounts));
    }

    // --- Inscrição fixa ------------------------------------------------------

    public function test_fixed_enrollment_generates_installments_matching_total(): void
    {
        $retreat = Retreat::factory()->create();
        $couple = Couple::factory()->create();

        $enrollment = $this->service->createEnrollment($retreat, $couple, [
            'payment_mode' => Enrollment::MODE_FIXED,
            'total_amount_cents' => 100000,
            'installments' => [
                ['due_date' => '2027-01-10'],
                ['due_date' => '2027-02-10'],
                ['due_date' => '2027-03-10'],
            ],
        ]);

        $this->assertSame(Enrollment::MODE_FIXED, $enrollment->payment_mode);
        $this->assertCount(3, $enrollment->installments);
        $this->assertSame(100000, $enrollment->installments->sum('amount_cents'));
        $this->assertSame([1, 2, 3], $enrollment->installments->pluck('installment_number')->all());
    }

    // --- Inscrição flexível ---------------------------------------------------

    public function test_flexible_enrollment_does_not_generate_installments(): void
    {
        $retreat = Retreat::factory()->create();
        $couple = Couple::factory()->create();

        $enrollment = $this->service->createEnrollment($retreat, $couple, [
            'payment_mode' => Enrollment::MODE_FLEXIBLE,
            'total_amount_cents' => 50000,
        ]);

        $this->assertSame(Enrollment::MODE_FLEXIBLE, $enrollment->payment_mode);
        $this->assertCount(0, $enrollment->installments);
    }

    // --- Pagamentos (modalidade fixa) -----------------------------------------

    protected function createFixedEnrollment(int $total = 90000, int $installments = 3): Enrollment
    {
        $retreat = Retreat::factory()->create();
        $couple = Couple::factory()->create();

        return $this->service->createEnrollment($retreat, $couple, [
            'payment_mode' => Enrollment::MODE_FIXED,
            'total_amount_cents' => $total,
            'installments' => array_fill(0, $installments, ['due_date' => now()->addMonth()]),
        ]);
    }

    public function test_full_payment_settles_all_installments(): void
    {
        $enrollment = $this->createFixedEnrollment(90000, 3);

        $this->service->registerPayment($enrollment, [
            'amount_cents' => 90000,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
        ], null);

        $enrollment->refresh()->load('installments.allocations.payment');

        $this->assertSame(0, $enrollment->balanceCents());
        $this->assertSame(90000, $enrollment->totalPaidCents());
        $this->assertTrue($enrollment->installments->every(fn (Installment $i) => $i->status() === Installment::STATUS_PAID));
    }

    public function test_partial_payment_leaves_remaining_balance(): void
    {
        $enrollment = $this->createFixedEnrollment(90000, 3); // 30000 each

        $this->service->registerPayment($enrollment, [
            'amount_cents' => 10000,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
        ], null);

        $enrollment->refresh()->load('installments.allocations.payment');

        $this->assertSame(80000, $enrollment->balanceCents());

        $first = $enrollment->installments->sortBy('installment_number')->first();
        $this->assertSame(Installment::STATUS_PARTIAL, $first->status());
        $this->assertSame(20000, $first->balanceCents());
    }

    public function test_multiple_payments_can_settle_a_single_installment(): void
    {
        $enrollment = $this->createFixedEnrollment(90000, 3); // 30000 each
        $first = $enrollment->installments->sortBy('installment_number')->first();

        $this->service->registerPayment($enrollment, [
            'amount_cents' => 10000,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
            'installment_ids' => [$first->id],
        ], null);

        $this->service->registerPayment($enrollment->refresh(), [
            'amount_cents' => 20000,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
            'installment_ids' => [$first->id],
        ], null);

        $first->refresh()->load('allocations.payment');

        $this->assertSame(Installment::STATUS_PAID, $first->status());
        $this->assertSame(0, $first->balanceCents());
        $this->assertCount(2, $first->allocations);
    }

    public function test_single_payment_can_settle_multiple_installments(): void
    {
        $enrollment = $this->createFixedEnrollment(90000, 3); // 30000 each

        $this->service->registerPayment($enrollment, [
            'amount_cents' => 60000,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_TRANSFER,
        ], null);

        $enrollment->refresh()->load('installments.allocations.payment');
        $ordered = $enrollment->installments->sortBy('installment_number')->values();

        $this->assertSame(Installment::STATUS_PAID, $ordered[0]->status());
        $this->assertSame(Installment::STATUS_PAID, $ordered[1]->status());
        $this->assertSame(Installment::STATUS_PENDING, $ordered[2]->status());
    }

    public function test_installment_can_be_anticipated_out_of_order(): void
    {
        $enrollment = $this->createFixedEnrollment(90000, 3); // 30000 each
        $third = $enrollment->installments->sortBy('installment_number')->values()[2];

        $this->service->registerPayment($enrollment, [
            'amount_cents' => 30000,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
            'installment_ids' => [$third->id],
        ], null);

        $third->refresh()->load('allocations.payment');
        $others = $enrollment->refresh()->installments->where('id', '!=', $third->id)->load('allocations.payment');

        $this->assertSame(Installment::STATUS_PAID, $third->status());
        $this->assertTrue($others->every(fn (Installment $i) => $i->status() === Installment::STATUS_PENDING));
    }

    public function test_payment_exceeding_balance_is_rejected(): void
    {
        $enrollment = $this->createFixedEnrollment(90000, 3);

        $this->expectException(ValidationException::class);

        $this->service->registerPayment($enrollment, [
            'amount_cents' => 90001,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
        ], null);
    }

    public function test_payment_of_zero_or_negative_amount_is_rejected(): void
    {
        $enrollment = $this->createFixedEnrollment(90000, 3);

        $this->expectException(ValidationException::class);

        $this->service->registerPayment($enrollment, [
            'amount_cents' => 0,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
        ], null);
    }

    public function test_allocation_to_an_installment_from_another_enrollment_is_rejected(): void
    {
        $enrollmentA = $this->createFixedEnrollment(90000, 3);
        $enrollmentB = $this->createFixedEnrollment(90000, 3);
        $foreignInstallment = $enrollmentB->installments->first();

        $this->expectException(ValidationException::class);

        $this->service->registerPayment($enrollmentA, [
            'amount_cents' => 30000,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
            'installment_ids' => [$foreignInstallment->id],
        ], null);
    }

    // --- Pagamentos (modalidade flexível) --------------------------------------

    public function test_flexible_payment_is_recorded_without_installments(): void
    {
        $retreat = Retreat::factory()->create();
        $couple = Couple::factory()->create();
        $enrollment = $this->service->createEnrollment($retreat, $couple, [
            'payment_mode' => Enrollment::MODE_FLEXIBLE,
            'total_amount_cents' => 50000,
        ]);

        $this->service->registerPayment($enrollment, [
            'amount_cents' => 12345,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_CASH,
        ], null);

        $enrollment->refresh();

        $this->assertSame(12345, $enrollment->totalPaidCents());
        $this->assertSame(0, $enrollment->payments->first()->allocations()->count());
    }

    public function test_flexible_enrollment_rejects_installment_allocation(): void
    {
        $retreat = Retreat::factory()->create();
        $couple = Couple::factory()->create();
        $enrollment = $this->service->createEnrollment($retreat, $couple, [
            'payment_mode' => Enrollment::MODE_FLEXIBLE,
            'total_amount_cents' => 50000,
        ]);

        $this->expectException(ValidationException::class);

        $this->service->registerPayment($enrollment, [
            'amount_cents' => 10000,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_CASH,
            'installment_ids' => [999],
        ], null);
    }

    // --- Estorno ---------------------------------------------------------------

    public function test_reversal_excludes_payment_from_totals_and_preserves_history(): void
    {
        $enrollment = $this->createFixedEnrollment(90000, 3);

        $payment = $this->service->registerPayment($enrollment, [
            'amount_cents' => 30000,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
        ], null);

        $this->assertSame(60000, $enrollment->refresh()->balanceCents());

        $admin = User::factory()->create();
        $this->service->reversePayment($payment, 'Pagamento duplicado por engano', $admin);

        $enrollment->refresh()->load('installments.allocations.payment');

        $this->assertSame(90000, $enrollment->balanceCents());
        $this->assertSame(0, $enrollment->totalPaidCents());

        // History is preserved, not deleted.
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertNotNull($payment->refresh()->reversed_at);
        $this->assertDatabaseCount('payment_allocations', 1);
    }

    public function test_reversing_an_already_reversed_payment_is_rejected(): void
    {
        $enrollment = $this->createFixedEnrollment(90000, 3);

        $payment = $this->service->registerPayment($enrollment, [
            'amount_cents' => 30000,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
        ], null);

        $admin = User::factory()->create();
        $this->service->reversePayment($payment, 'Primeiro estorno', $admin);

        $this->expectException(ValidationException::class);

        $this->service->reversePayment($payment->refresh(), 'Segundo estorno', $admin);
    }

    // --- Edição financeira bloqueada após pagamento -----------------------------

    public function test_cannot_change_payment_mode_or_total_after_payments_exist(): void
    {
        $enrollment = $this->createFixedEnrollment(90000, 3);

        $this->service->registerPayment($enrollment, [
            'amount_cents' => 10000,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
        ], null);

        $this->expectException(ValidationException::class);

        $this->service->assertFinancialsAreEditable($enrollment->refresh(), [
            'total_amount_cents' => 50000,
        ]);
    }

    public function test_can_still_edit_financials_without_payment_history(): void
    {
        $enrollment = $this->createFixedEnrollment(90000, 3);

        $this->service->assertFinancialsAreEditable($enrollment, [
            'total_amount_cents' => 50000,
        ]);

        $this->assertTrue(true);
    }
}
