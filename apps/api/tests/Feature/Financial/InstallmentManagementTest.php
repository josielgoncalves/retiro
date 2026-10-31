<?php

namespace Tests\Feature\Financial;

use App\Models\AuditLog;
use App\Models\Couple;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Retreat;
use App\Models\User;
use App\Services\FinancialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InstallmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private FinancialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FinancialService::class);
    }

    private function enrollment(int $total = 90000, int $count = 3): Enrollment
    {
        return $this->service->createEnrollment(Retreat::factory()->create(), Couple::factory()->create(), [
            'payment_mode' => Enrollment::MODE_FIXED,
            'total_amount_cents' => $total,
            'installments' => array_fill(0, $count, ['due_date' => '2027-01-10']),
        ])->load('installments.allocations.payment');
    }

    public function test_monthly_and_weekly_previews_are_generated_correctly(): void
    {
        $monthly = $this->service->previewInstallments(90000, 6, '2027-01-10', 'monthly');
        $weekly = $this->service->previewInstallments(30000, 3, '2027-01-10', 'weekly');

        $this->assertSame('2027-06-10', $monthly[5]['due_date']);
        $this->assertSame(['2027-01-10', '2027-01-17', '2027-01-24'], array_column($weekly, 'due_date'));
        $this->assertSame(90000, array_sum(array_column($monthly, 'amount_cents')));
    }

    public function test_monthly_preview_preserves_original_day_after_short_month(): void
    {
        $leap = $this->service->previewInstallments(300, 3, '2028-01-31', 'monthly');
        $regular = $this->service->previewInstallments(300, 3, '2027-01-31', 'monthly');

        $this->assertSame(['2028-01-31', '2028-02-29', '2028-03-31'], array_column($leap, 'due_date'));
        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31'], array_column($regular, 'due_date'));
    }

    public function test_custom_installment_sum_must_match_contract(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->createEnrollment(Retreat::factory()->create(), Couple::factory()->create(), [
            'payment_mode' => Enrollment::MODE_FIXED,
            'total_amount_cents' => 10000,
            'installments' => [
                ['due_date' => '2027-01-10', 'amount_cents' => 4000],
                ['due_date' => '2027-02-10', 'amount_cents' => 4000],
            ],
        ]);
    }

    public function test_due_date_of_paid_installment_can_change_but_amount_cannot(): void
    {
        $enrollment = $this->enrollment();
        $first = $enrollment->installments->first();
        $this->service->registerPayment($enrollment, [
            'amount_cents' => $first->amount_cents,
            'paid_at' => '2026-12-01',
            'payment_method' => Payment::METHOD_PIX,
            'installment_ids' => [$first->id],
        ], null);

        $updated = $this->service->updateInstallment($first->refresh(), ['due_date' => '2027-01-20']);
        $this->assertSame('2027-01-20', $updated->due_date->toDateString());

        $this->expectException(ValidationException::class);
        $this->service->updateInstallment($updated, ['due_date' => '2027-01-20', 'amount_cents' => 31000, 'compensating_installment_id' => $enrollment->installments[1]->id]);
    }

    public function test_partial_installment_cannot_be_reduced_below_received_amount(): void
    {
        $enrollment = $this->enrollment();
        $first = $enrollment->installments->first();
        $this->service->registerPayment($enrollment, ['amount_cents' => 10000, 'paid_at' => now(), 'payment_method' => Payment::METHOD_PIX], null);

        $this->expectException(ValidationException::class);
        $this->service->updateInstallment($first->refresh(), ['due_date' => '2027-01-20', 'amount_cents' => 9999, 'compensating_installment_id' => $enrollment->installments[1]->id]);
    }

    public function test_value_change_is_compensated_and_audited(): void
    {
        $enrollment = $this->enrollment();
        [$first, $second] = $enrollment->installments->all();
        $this->service->updateInstallment($first, ['due_date' => '2027-01-11', 'amount_cents' => 35000, 'compensating_installment_id' => $second->id]);

        $this->assertSame(90000, (int) $enrollment->installments()->sum('amount_cents'));
        $this->assertSame(25000, $second->refresh()->amount_cents);
        $this->assertDatabaseHas('audit_logs', ['action' => 'installment.updated', 'entity_id' => $first->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'installment.compensated', 'entity_id' => $second->id]);
    }

    public function test_regeneration_preserves_paid_installment_payments_and_values(): void
    {
        $enrollment = $this->enrollment();
        $first = $enrollment->installments->first();
        $payment = $this->service->registerPayment($enrollment, ['amount_cents' => 30000, 'paid_at' => now(), 'payment_method' => Payment::METHOD_PIX], null);
        $beforeAmounts = $enrollment->installments()->pluck('amount_cents', 'id')->all();

        $this->service->regeneratePendingDueDates($enrollment->refresh(), '2027-04-30', 'monthly');

        $this->assertSame('2027-01-10', $first->refresh()->due_date->toDateString());
        $this->assertSame($beforeAmounts, $enrollment->installments()->pluck('amount_cents', 'id')->all());
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertTrue(AuditLog::where('action', 'installment.due_dates_regenerated')->exists());
    }

    public function test_regeneration_can_reduce_total_quantity_and_preserves_paid_history(): void
    {
        $enrollment = $this->enrollment(180000, 10);
        $paidInstallment = $enrollment->installments->last();
        $originalPaidAmount = $paidInstallment->amount_cents;
        $originalPaidDueDate = $paidInstallment->due_date->toDateString();
        $payment = $this->service->registerPayment($enrollment, [
            'amount_cents' => $originalPaidAmount,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
            'installment_ids' => [$paidInstallment->id],
        ], null);

        $preview = $this->service->previewRegeneratedInstallments($enrollment->refresh(), 8, '2027-01-10', 'monthly');
        $this->assertCount(8, $preview);
        $this->assertSame(180000, array_sum(array_column($preview, 'new_amount_cents')));
        $this->assertTrue($preview[0]['preserved']);

        $result = $this->service->regenerateInstallments($enrollment->refresh(), 8, '2027-01-10', 'monthly');

        $this->assertCount(8, $result);
        $this->assertSame(180000, (int) $enrollment->installments()->sum('amount_cents'));
        $this->assertSame(1, $paidInstallment->refresh()->installment_number);
        $this->assertSame($originalPaidAmount, $paidInstallment->amount_cents);
        $this->assertSame($originalPaidDueDate, $paidInstallment->due_date->toDateString());
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'installment_id' => $paidInstallment->id,
            'amount_cents' => $originalPaidAmount,
        ]);
        $this->assertTrue(AuditLog::where('action', 'installment.created_by_regeneration')->exists());
    }

    public function test_regeneration_endpoint_previews_and_confirms_new_quantity(): void
    {
        $enrollment = $this->enrollment(180000, 10);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $payload = [
            'installment_count' => 8,
            'first_due_date' => '2027-01-10',
            'periodicity' => 'monthly',
        ];

        $this->actingAs($admin)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/installments/regenerate", $payload)
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonPath('data.7.installment_number', 8);

        $this->actingAs($admin)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/installments/regenerate", [...$payload, 'confirm' => true])
            ->assertOk()
            ->assertJsonCount(8, 'data');

        $this->assertDatabaseCount('installments', 8);
    }

    public function test_operator_cannot_edit_installment_from_any_enrollment(): void
    {
        $installment = $this->enrollment()->installments->first();
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)->patchJson("/api/v1/installments/{$installment->id}", ['due_date' => '2027-02-01'])->assertForbidden();
    }
}
