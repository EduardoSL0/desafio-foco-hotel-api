<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Promotion> */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'room_id' => null,
            'name' => 'Promoção '.fake()->word(),
            'discount_percent' => 20,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
            'active' => true,
        ];
    }
}
