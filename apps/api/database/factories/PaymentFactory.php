<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'amount_cents' => 10000,
            'paid_at' => now(),
            'payment_method' => Payment::METHOD_PIX,
            'reference' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }
}
