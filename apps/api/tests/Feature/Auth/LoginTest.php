<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum only starts a session for requests it recognises as coming
        // from the SPA (matched against SANCTUM_STATEFUL_DOMAINS via the
        // Origin/Referer header), exactly like the browser would send.
        $this->withHeader('Origin', 'http://localhost:8080');
    }

    /**
     * Fetch the CSRF cookie like the SPA does, and carry it over to the next
     * request exactly as axios would (same raw, still-encrypted cookie value
     * echoed back as the X-XSRF-TOKEN header).
     */
    protected function withCsrfToken(): static
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN');

        return $this->withCookie('XSRF-TOKEN', $cookie->getValue())
            ->withHeader('X-XSRF-TOKEN', $cookie->getValue());
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $response = $this->withCsrfToken()->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJsonPath('data.email', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->withCsrfToken()->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inativo@example.com',
            'password' => bcrypt('secret123'),
            'active' => false,
        ]);

        $response = $this->withCsrfToken()->postJson('/api/v1/auth/login', [
            'email' => 'inativo@example.com',
            'password' => 'secret123',
        ]);

        $response->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_guest_cannot_fetch_profile(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }

    public function test_user_can_logout(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->withCsrfToken()->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ])->assertOk();

        $this->assertAuthenticated();

        $this->withCsrfToken()
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // Sanctum's "auth:sanctum" middleware calls Auth::shouldUse('sanctum')
        // while authenticating, which only matters here because the test
        // client reuses one container across several simulated requests (a
        // real request never outlives a single PHP-FPM process). Clear the
        // cached guards so the next check reflects the just-invalidated
        // session instead of a stale in-memory guard from an earlier call.
        $this->app->make('auth')->forgetGuards();

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_too_many_attempts(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->withCsrfToken()->postJson('/api/v1/auth/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->withCsrfToken()->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(429);
    }
}
