<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_cannot_list_users(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_operator_cannot_create_users(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $response = $this->actingAs($operator)->postJson('/api/v1/users', [
            'name' => 'Novo Usuário',
            'email' => 'novo@example.com',
            'password' => 'password123',
            'role' => User::ROLE_OPERATOR,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_list_and_create_users(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->getJson('/api/v1/users')->assertOk();

        $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'Novo Usuário',
            'email' => 'novo@example.com',
            'password' => 'password123',
            'role' => User::ROLE_OPERATOR,
        ])->assertCreated();
    }

    public function test_operator_can_manage_couples_and_payments(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

        $this->actingAs($operator)->postJson('/api/v1/couples', [
            'partner_one_name' => 'Pedro',
            'partner_two_name' => 'Ana',
            'phone' => '+55 11 99999-0000',
        ])->assertCreated();
    }

    public function test_inactive_user_is_rejected_even_with_valid_session(): void
    {
        $inactive = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => false]);

        $this->actingAs($inactive)->getJson('/api/v1/retreats')->assertForbidden();
    }
}
