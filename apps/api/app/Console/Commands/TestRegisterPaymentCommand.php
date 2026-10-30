<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Services\FinancialService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Thin CLI wrapper around FinancialService::registerPayment, used only by
 * the automated test suite to exercise real concurrent requests as separate
 * OS processes (two genuinely independent database connections), which a
 * single PHPUnit process cannot simulate once RefreshDatabase wraps the
 * test body in an uncommitted transaction.
 */
class TestRegisterPaymentCommand extends Command
{
    protected $signature = 'app:test-register-payment {enrollment} {amount}';

    protected $description = 'Testing helper: registers a payment for an enrollment and reports success/failure via exit code.';

    public function handle(FinancialService $financialService): int
    {
        $enrollment = Enrollment::find((int) $this->argument('enrollment'));

        if (! $enrollment) {
            $this->error('enrollment not found');

            return self::FAILURE;
        }

        try {
            $financialService->registerPayment($enrollment, [
                'amount_cents' => (int) $this->argument('amount'),
                'paid_at' => now(),
                'payment_method' => 'pix',
            ], null);

            $this->info('ok');

            return self::SUCCESS;
        } catch (ValidationException $e) {
            $this->error('rejected: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
