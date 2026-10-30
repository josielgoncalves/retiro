<?php

namespace Tests\Feature\Api;

use App\Models\Couple;
use App\Models\Enrollment;
use App\Models\Retreat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetreatCoupleEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_admin_can_create_a_retreat(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/retreats', [
            'name' => 'Retiro de Casais 2027',
            'start_date' => '2027-03-10',
            'end_date' => '2027-03-12',
            'location' => 'Casa de Retiros',
            'registration_open' => true,
            'status' => 'draft',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Retiro de Casais 2027');
        $this->assertDatabaseHas('retreats', ['name' => 'Retiro de Casais 2027']);
    }

    public function test_admin_can_register_a_couple(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/couples', [
            'partner_one_name' => 'João Silva',
            'partner_two_name' => 'Maria Silva',
            'phone' => '+55 11 91234-5678',
            'email' => 'joao.maria@example.com',
        ]);

        $response->assertCreated()->assertJsonPath('data.partner_one_name', 'João Silva');
        $this->assertDatabaseHas('couples', ['email' => 'joao.maria@example.com']);
    }

    public function test_couples_list_supports_search_and_pagination(): void
    {
        Couple::factory()->create(['partner_one_name' => 'Carlos Pereira']);
        Couple::factory()->create(['partner_one_name' => 'Outro Nome']);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/couples?search=Carlos');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Carlos Pereira', $response->json('data.0.partner_one_name'));
    }

    public function test_admin_can_enroll_a_couple_in_fixed_mode(): void
    {
        $retreat = Retreat::factory()->create();
        $couple = Couple::factory()->create();

        $response = $this->actingAs($this->admin)->postJson('/api/v1/enrollments', [
            'retreat_id' => $retreat->id,
            'couple_id' => $couple->id,
            'payment_mode' => 'fixed',
            'total_amount_cents' => 60000,
            'installments' => [
                ['due_date' => '2027-01-10'],
                ['due_date' => '2027-02-10'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_mode', 'fixed')
            ->assertJsonCount(2, 'data.installments');

        $this->assertSame(60000, Enrollment::first()->installments->sum('amount_cents'));
    }

    public function test_admin_can_enroll_a_couple_in_flexible_mode(): void
    {
        $retreat = Retreat::factory()->create();
        $couple = Couple::factory()->create();

        $response = $this->actingAs($this->admin)->postJson('/api/v1/enrollments', [
            'retreat_id' => $retreat->id,
            'couple_id' => $couple->id,
            'payment_mode' => 'flexible',
            'total_amount_cents' => 50000,
        ]);

        $response->assertCreated()->assertJsonPath('data.payment_mode', 'flexible');
        $this->assertSame(0, Enrollment::first()->installments()->count());
    }

    public function test_cannot_enroll_the_same_couple_twice_in_the_same_retreat(): void
    {
        $retreat = Retreat::factory()->create();
        $couple = Couple::factory()->create();
        Enrollment::factory()->create(['retreat_id' => $retreat->id, 'couple_id' => $couple->id]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/enrollments', [
            'retreat_id' => $retreat->id,
            'couple_id' => $couple->id,
            'payment_mode' => 'flexible',
            'total_amount_cents' => 50000,
        ]);

        $response->assertUnprocessable();
    }

    public function test_enrollment_can_be_cancelled_without_erasing_history(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create();

        $response = $this->actingAs($this->admin)->postJson("/api/v1/enrollments/{$enrollment->id}/cancel");

        $response->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'status' => 'cancelled']);
    }
}
