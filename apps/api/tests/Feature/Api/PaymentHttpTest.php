<?php

namespace Tests\Feature\Api;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentHttpTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_admin_can_register_a_payment(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create(['total_amount_cents' => 50000]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/enrollments/{$enrollment->id}/payments", [
            'amount_cents' => 20000,
            'paid_at' => now()->toDateTimeString(),
            'payment_method' => 'pix',
        ]);

        $response->assertCreated()->assertJsonPath('data.amount_cents', 20000);
        $this->assertDatabaseHas('payments', ['enrollment_id' => $enrollment->id, 'amount_cents' => 20000]);
    }

    public function test_payment_above_balance_is_rejected_via_api(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create(['total_amount_cents' => 50000]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/enrollments/{$enrollment->id}/payments", [
            'amount_cents' => 60000,
            'paid_at' => now()->toDateTimeString(),
            'payment_method' => 'pix',
        ]);

        $response->assertUnprocessable();
    }

    public function test_admin_can_reverse_a_payment_and_balance_is_recalculated(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create(['total_amount_cents' => 50000]);

        $paymentResponse = $this->actingAs($this->admin)->postJson("/api/v1/enrollments/{$enrollment->id}/payments", [
            'amount_cents' => 20000,
            'paid_at' => now()->toDateTimeString(),
            'payment_method' => 'pix',
        ]);

        $paymentId = $paymentResponse->json('data.id');

        $reverseResponse = $this->actingAs($this->admin)->postJson("/api/v1/payments/{$paymentId}/reverse", [
            'reason' => 'Valor lançado em duplicidade',
        ]);

        $reverseResponse->assertOk();
        $this->assertNotNull($reverseResponse->json('data.reversed_at'));

        $detail = $this->actingAs($this->admin)->getJson("/api/v1/enrollments/{$enrollment->id}");
        $detail->assertOk()->assertJsonPath('data.balance_cents', 50000);
    }

    public function test_cannot_edit_payment_fields_belonging_to_another_enrollment_scope(): void
    {
        $enrollmentA = Enrollment::factory()->flexible()->create(['total_amount_cents' => 50000]);
        $enrollmentB = Enrollment::factory()->flexible()->create(['total_amount_cents' => 50000]);

        $this->actingAs($this->admin)->postJson("/api/v1/enrollments/{$enrollmentA->id}/payments", [
            'amount_cents' => 10000,
            'paid_at' => now()->toDateTimeString(),
            'payment_method' => 'pix',
        ])->assertCreated();

        $listB = $this->actingAs($this->admin)->getJson("/api/v1/enrollments/{$enrollmentB->id}/payments");

        $listB->assertOk()->assertJsonCount(0, 'data');
    }
}
