<?php

namespace Tests\Feature\Api;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_payment_registration_is_audited(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create(['total_amount_cents' => 50000]);

        $this->actingAs($this->admin)->postJson("/api/v1/enrollments/{$enrollment->id}/payments", [
            'amount_cents' => 10000,
            'paid_at' => now()->toDateTimeString(),
            'payment_method' => 'pix',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.created',
            'user_id' => $this->admin->id,
            'entity_type' => 'App\\Models\\Payment',
        ]);
    }

    public function test_payment_reversal_is_audited(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create(['total_amount_cents' => 50000]);

        $paymentResponse = $this->actingAs($this->admin)->postJson("/api/v1/enrollments/{$enrollment->id}/payments", [
            'amount_cents' => 10000,
            'paid_at' => now()->toDateTimeString(),
            'payment_method' => 'pix',
        ]);

        $this->actingAs($this->admin)->postJson('/api/v1/payments/'.$paymentResponse->json('data.id').'/reverse', [
            'reason' => 'Teste de estorno',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.reversed',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_user_creation_is_audited(): void
    {
        $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'name' => 'Operador Teste',
            'email' => 'operador@example.com',
            'password' => 'password123',
            'role' => User::ROLE_OPERATOR,
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_access_link_regeneration_is_audited(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create();

        $this->actingAs($this->admin)
            ->postJson("/api/v1/enrollments/{$enrollment->id}/access-link/regenerate")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'portal_token.regenerated',
            'user_id' => $this->admin->id,
        ]);
    }
}
