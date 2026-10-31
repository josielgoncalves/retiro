<?php

namespace Tests\Feature\Api;

use App\Models\Retreat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RetreatCustomizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Retiro 2027',
            'start_date' => '2027-05-01',
            'end_date' => '2027-05-03',
            'status' => Retreat::STATUS_DRAFT,
            'registration_open' => true,
            'pix_key_type' => 'email',
            'pix_key' => 'retiro@example.com',
            'pix_beneficiary_name' => 'Comunidade Retiro',
            'receipt_whatsapp' => '+55 63 99999-0000',
        ], $overrides);
    }

    private function fakePng(string $name = 'cover.png'): UploadedFile
    {
        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );

        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    public function test_admin_can_create_retreat_with_image_and_payment_information(): void
    {
        $response = $this->actingAs($this->admin)->post('/api/v1/retreats', $this->validData([
            'cover_image' => $this->fakePng(),
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.pix_key', 'retiro@example.com')
            ->assertJsonPath('data.receipt_whatsapp', '+55 63 99999-0000');

        $retreat = Retreat::firstOrFail();
        $this->assertNotNull($retreat->cover_image_path);
        $this->assertStringStartsWith("retreats/{$retreat->id}/covers/", $retreat->cover_image_path);
        Storage::disk('local')->assertExists($retreat->cover_image_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'retreat.created', 'entity_id' => $retreat->id]);
    }

    public function test_image_can_be_replaced_and_removed(): void
    {
        $retreat = Retreat::factory()->create();
        $this->actingAs($this->admin)->post("/api/v1/retreats/{$retreat->id}", [
            '_method' => 'PATCH',
            'cover_image' => $this->fakePng('primeira.png'),
        ])->assertOk();
        $firstPath = $retreat->refresh()->cover_image_path;

        $this->actingAs($this->admin)->post("/api/v1/retreats/{$retreat->id}", [
            '_method' => 'PATCH',
            'cover_image' => $this->fakePng('segunda.png'),
        ])->assertOk();
        $secondPath = $retreat->refresh()->cover_image_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertExists($secondPath);

        $this->actingAs($this->admin)->patchJson("/api/v1/retreats/{$retreat->id}", [
            'remove_cover_image' => true,
        ])->assertOk()->assertJsonPath('data.cover_image_url', null);
        $this->assertNull($retreat->refresh()->cover_image_path);
    }

    public function test_invalid_upload_is_rejected_and_existing_image_is_preserved(): void
    {
        $retreat = Retreat::factory()->create();
        $this->actingAs($this->admin)->post("/api/v1/retreats/{$retreat->id}", [
            '_method' => 'PATCH',
            'cover_image' => $this->fakePng('valida.png'),
        ])->assertOk();
        $originalPath = $retreat->refresh()->cover_image_path;

        $this->actingAs($this->admin)->post("/api/v1/retreats/{$retreat->id}", [
            '_method' => 'PATCH',
            'cover_image' => UploadedFile::fake()->createWithContent('malware.php.jpg', '<?php echo "executed";'),
        ])->assertUnprocessable()->assertJsonValidationErrors('cover_image');

        $this->assertSame($originalPath, $retreat->refresh()->cover_image_path);
        Storage::disk('local')->assertExists($originalPath);
    }

    public function test_cover_endpoint_serves_only_the_requested_retreat_image(): void
    {
        $first = Retreat::factory()->create(['cover_image_path' => 'retreats/1/covers/first.jpg']);
        $second = Retreat::factory()->create(['cover_image_path' => 'retreats/2/covers/second.jpg']);
        Storage::disk('local')->put($first->cover_image_path, 'first-image');
        Storage::disk('local')->put($second->cover_image_path, 'second-image');

        $response = $this->get("/api/v1/public/retreats/{$first->id}/cover");

        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('first-image', $response->streamedContent());
        $this->get('/api/v1/public/retreats/999999/cover')->assertNotFound();
    }

    public function test_cover_url_is_absolute_and_changes_after_replacement(): void
    {
        $retreat = Retreat::factory()->create();

        $this->actingAs($this->admin)->post("/api/v1/retreats/{$retreat->id}", [
            '_method' => 'PATCH',
            'cover_image' => $this->fakePng('primeira.png'),
        ])->assertOk();
        $firstUrl = $this->actingAs($this->admin)->getJson("/api/v1/retreats/{$retreat->id}")
            ->json('data.cover_image_url');

        $this->actingAs($this->admin)->post("/api/v1/retreats/{$retreat->id}", [
            '_method' => 'PATCH',
            'cover_image' => $this->fakePng('segunda.png'),
        ])->assertOk();
        $secondUrl = $this->actingAs($this->admin)->getJson("/api/v1/retreats/{$retreat->id}")
            ->json('data.cover_image_url');

        $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/api/v1/public/retreats/', $firstUrl);
        $this->assertStringContainsString('?v=', $firstUrl);
        $this->assertNotSame($firstUrl, $secondUrl);
    }

    public function test_operator_cannot_create_or_customize_retreats(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);
        $retreat = Retreat::factory()->create();

        $this->actingAs($operator)->postJson('/api/v1/retreats', $this->validData())->assertForbidden();
        $this->actingAs($operator)->patchJson("/api/v1/retreats/{$retreat->id}", ['pix_key' => 'forbidden'])->assertForbidden();
    }
}
