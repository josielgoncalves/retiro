<?php

namespace Tests\Feature\Api;

use App\Models\Announcement;
use App\Models\Couple;
use App\Models\Enrollment;
use App\Services\PortalTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_returns_enrollment_summary(): void
    {
        $couple = Couple::factory()->create([
            'partner_one_name' => 'Rafael',
            'partner_two_name' => 'Juliana',
            'notes' => 'Observação administrativa sensível',
        ]);
        $enrollment = Enrollment::factory()->flexible()->create([
            'couple_id' => $couple->id,
            'total_amount_cents' => 50000,
        ]);

        ['plainText' => $plainText] = app(PortalTokenService::class)->generate($enrollment);

        $response = $this->getJson("/api/v1/public/portal/{$plainText}");

        $response->assertOk()
            ->assertJsonPath('data.couple.partner_one_name', 'Rafael')
            ->assertJsonPath('data.couple.partner_two_name', 'Juliana')
            ->assertJsonPath('data.total_amount_cents', 50000);
    }

    public function test_portal_response_never_exposes_internal_or_administrative_data(): void
    {
        $couple = Couple::factory()->create(['notes' => 'Informação sigilosa de bastidor']);
        $enrollment = Enrollment::factory()->flexible()->create(['couple_id' => $couple->id]);

        ['plainText' => $plainText] = app(PortalTokenService::class)->generate($enrollment);

        $response = $this->getJson("/api/v1/public/portal/{$plainText}");

        $response->assertOk();
        $json = $response->json('data');

        $this->assertArrayNotHasKey('id', $json);
        $this->assertArrayNotHasKey('notes', $json['couple']);
        $this->assertStringNotContainsString('sigilosa', $response->getContent());
    }

    public function test_revoked_token_is_rejected(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create();
        $service = app(PortalTokenService::class);

        ['plainText' => $plainText] = $service->generate($enrollment);
        $service->revokeAll($enrollment);

        $this->getJson("/api/v1/public/portal/{$plainText}")->assertNotFound();
    }

    public function test_nonexistent_token_is_rejected(): void
    {
        $this->getJson('/api/v1/public/portal/this-token-does-not-exist')->assertNotFound();
    }

    public function test_expired_token_is_rejected(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create();

        ['plainText' => $plainText] = app(PortalTokenService::class)->generate($enrollment, now()->subDay());

        $this->getJson("/api/v1/public/portal/{$plainText}")->assertNotFound();
    }

    public function test_private_portal_headers_are_present(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create();
        ['plainText' => $plainText] = app(PortalTokenService::class)->generate($enrollment);

        $response = $this->getJson("/api/v1/public/portal/{$plainText}");

        $response->assertHeader('Referrer-Policy', 'no-referrer');
        // Symfony's ResponseHeaderBag always appends ", private" unless
        // "public"/"s-maxage" is set — stronger than bare no-store, not weaker.
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_only_published_announcements_within_validity_are_visible(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create();
        $retreatId = $enrollment->retreat_id;

        Announcement::factory()->create(['retreat_id' => $retreatId, 'title' => 'Rascunho', 'published_at' => null]);
        Announcement::factory()->create([
            'retreat_id' => $retreatId, 'title' => 'Expirado', 'published_at' => now()->subWeek(), 'visible_until' => now()->subDay(),
        ]);
        Announcement::factory()->create([
            'retreat_id' => $retreatId, 'title' => 'Publicado válido', 'published_at' => now()->subDay(),
        ]);

        ['plainText' => $plainText] = app(PortalTokenService::class)->generate($enrollment);

        $response = $this->getJson("/api/v1/public/portal/{$plainText}/announcements");

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('Publicado válido', $response->json('data.0.title'));
    }

    public function test_announcements_from_other_retreats_are_not_shown(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create();
        $otherRetreat = \App\Models\Retreat::factory()->create();

        Announcement::factory()->create([
            'retreat_id' => $otherRetreat->id, 'title' => 'De outro retiro', 'published_at' => now()->subDay(),
        ]);

        ['plainText' => $plainText] = app(PortalTokenService::class)->generate($enrollment);

        $response = $this->getJson("/api/v1/public/portal/{$plainText}/announcements");

        $response->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_regenerating_access_link_revokes_the_previous_one(): void
    {
        $enrollment = Enrollment::factory()->flexible()->create();
        $service = app(PortalTokenService::class);

        ['plainText' => $oldToken] = $service->generate($enrollment);
        $service->regenerate($enrollment);

        $this->getJson("/api/v1/public/portal/{$oldToken}")->assertNotFound();
    }
}
