<?php

namespace Tests\Feature\Financial;

use App\Models\Couple;
use App\Models\Enrollment;
use App\Models\Retreat;
use App\Services\FinancialService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * This test intentionally does NOT use RefreshDatabase: that trait wraps the
 * whole test body in one uncommitted transaction, which a genuinely separate
 * OS process could never see. To prove the locking in
 * FinancialService::registerPayment() actually prevents overselling under
 * real concurrent requests, we spawn two independent `php artisan` processes
 * against the same committed row and assert on the final, persisted state.
 *
 * Both this process and the spawned ones connect to retiro_casais_testing
 * because APP_ENV=testing makes Laravel load .env.testing instead of .env
 * (see phpunit.xml and .env.testing) — that's what keeps this test, and
 * migrate:fresh in tearDown, off the development database.
 */
class ConcurrentPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);

        parent::tearDown();
    }

    public function test_two_simultaneous_payments_never_oversell_the_balance(): void
    {
        $retreat = Retreat::factory()->create();
        $couple = Couple::factory()->create();

        $enrollment = app(FinancialService::class)->createEnrollment($retreat, $couple, [
            'payment_mode' => Enrollment::MODE_FLEXIBLE,
            'total_amount_cents' => 10000,
        ]);

        // Two attempts of 6000 each: together they exceed the 10000 balance,
        // so at most one may succeed, however the two OS processes interleave.
        $processOne = Process::command(['php', 'artisan', 'app:test-register-payment', (string) $enrollment->id, '6000'])
            ->start();
        $processTwo = Process::command(['php', 'artisan', 'app:test-register-payment', (string) $enrollment->id, '6000'])
            ->start();

        $resultOne = $processOne->wait();
        $resultTwo = $processTwo->wait();

        $successCount = (int) $resultOne->successful() + (int) $resultTwo->successful();

        $this->assertSame(1, $successCount, 'Exactly one of the two concurrent payments should have succeeded.');

        $enrollment->refresh();
        $this->assertSame(6000, $enrollment->totalPaidCents());
        $this->assertSame(4000, $enrollment->balanceCents());
        $this->assertLessThanOrEqual($enrollment->total_amount_cents, $enrollment->totalPaidCents());
    }
}
