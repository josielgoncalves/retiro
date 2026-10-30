<?php

namespace Tests\Feature\Api;

use App\Models\Enrollment;
use App\Models\Retreat;
use App\Models\User;
use App\Services\FinancialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_dashboard_never_mixes_financial_results_from_different_retreats(): void
    {
        $retreatA = Retreat::factory()->create();
        $retreatB = Retreat::factory()->create();

        Enrollment::factory()->flexible()->create(['retreat_id' => $retreatA->id, 'total_amount_cents' => 100000]);
        Enrollment::factory()->flexible()->create(['retreat_id' => $retreatB->id, 'total_amount_cents' => 999999]);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/dashboard?retreat_id={$retreatA->id}");

        $response->assertOk()
            ->assertJsonPath('data.retreat_id', $retreatA->id)
            ->assertJsonPath('data.total_contracted_cents', 100000)
            ->assertJsonPath('data.active_enrollments_count', 1);
    }

    public function test_dashboard_excludes_reversed_payments_from_totals(): void
    {
        $retreat = Retreat::factory()->create();
        $enrollment = Enrollment::factory()->flexible()->create(['retreat_id' => $retreat->id, 'total_amount_cents' => 50000]);

        $service = app(FinancialService::class);
        $payment = $service->registerPayment($enrollment, [
            'amount_cents' => 20000,
            'paid_at' => now(),
            'payment_method' => 'pix',
        ], $this->admin);

        $service->reversePayment($payment, 'Erro de lançamento', $this->admin);

        $response = $this->actingAs($this->admin)->getJson("/api/v1/dashboard?retreat_id={$retreat->id}");

        $response->assertOk()->assertJsonPath('data.total_received_cents', 0);
    }

    public function test_csv_export_only_contains_data_from_the_requested_retreat(): void
    {
        $retreatA = Retreat::factory()->create();
        $retreatB = Retreat::factory()->create();

        $coupleA = \App\Models\Couple::factory()->create(['partner_one_name' => 'CasalRetiroA']);
        $coupleB = \App\Models\Couple::factory()->create(['partner_one_name' => 'CasalRetiroB']);

        Enrollment::factory()->flexible()->create(['retreat_id' => $retreatA->id, 'couple_id' => $coupleA->id]);
        Enrollment::factory()->flexible()->create(['retreat_id' => $retreatB->id, 'couple_id' => $coupleB->id]);

        $response = $this->actingAs($this->admin)->get("/api/v1/reports/financial/export?retreat_id={$retreatA->id}");

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('CasalRetiroA', $csv);
        $this->assertStringNotContainsString('CasalRetiroB', $csv);
    }
}
