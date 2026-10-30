<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\Retreat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'retreat_id' => Retreat::factory(),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'published_at' => null,
            'visible_until' => null,
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(['published_at' => now()->subDay()]);
    }

    public function draft(): static
    {
        return $this->state(['published_at' => null]);
    }
}
