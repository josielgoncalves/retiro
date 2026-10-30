<?php

namespace Database\Factories;

use App\Models\Couple;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Couple>
 */
class CoupleFactory extends Factory
{
    protected $model = Couple::class;

    public function definition(): array
    {
        return [
            'partner_one_name' => fake()->firstName('male').' '.fake()->lastName(),
            'partner_two_name' => fake()->firstName('female').' '.fake()->lastName(),
            'phone' => '+55 '.fake()->numerify('## #####-####'),
            'secondary_phone' => null,
            'email' => fake()->unique()->safeEmail(),
            'notes' => null,
        ];
    }
}
