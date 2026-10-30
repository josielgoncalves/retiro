<?php

namespace Database\Factories;

use App\Models\Couple;
use App\Models\Enrollment;
use App\Models\Retreat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'retreat_id' => Retreat::factory(),
            'couple_id' => Couple::factory(),
            'payment_mode' => Enrollment::MODE_FLEXIBLE,
            'total_amount_cents' => 100000,
            'status' => Enrollment::STATUS_ACTIVE,
            'registered_at' => now(),
        ];
    }

    public function fixed(): static
    {
        return $this->state(['payment_mode' => Enrollment::MODE_FIXED]);
    }

    public function flexible(): static
    {
        return $this->state(['payment_mode' => Enrollment::MODE_FLEXIBLE]);
    }
}
