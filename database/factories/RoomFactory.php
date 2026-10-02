<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Room> */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'name' => 'Quarto '.fake()->randomElement(['Standard', 'Luxo', 'Suíte', 'Master']).' '.fake()->numberBetween(100, 999),
            'description' => fake()->sentence(),
            'capacity' => 2,
            'inventory' => 1,
            'daily_price' => 100.00,
        ];
    }

    public function withoutRate(): static
    {
        return $this->state(['daily_price' => null]);
    }
}
