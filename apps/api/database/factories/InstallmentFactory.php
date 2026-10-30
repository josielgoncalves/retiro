<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Installment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Installment>
 */
class InstallmentFactory extends Factory
{
    protected $model = Installment::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory()->fixed(),
            'installment_number' => 1,
            'amount_cents' => 10000,
            'due_date' => now()->addMonth(),
        ];
    }
}
