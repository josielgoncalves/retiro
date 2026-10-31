<?php

namespace Database\Factories;

use App\Models\Retreat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Retreat>
 */
class RetreatFactory extends Factory
{
    protected $model = Retreat::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 month', '+3 months');

        return [
            'name' => 'Retiro '.fake()->monthName().' '.fake()->year(),
            'description' => fake()->sentence(),
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+2 days'),
            'location' => fake()->city(),
            'registration_open' => true,
            'status' => Retreat::STATUS_ACTIVE,
            'cover_image_path' => null,
            'pix_key_type' => null,
            'pix_key' => null,
            'pix_beneficiary_name' => null,
            'receipt_whatsapp' => null,
        ];
    }
}
