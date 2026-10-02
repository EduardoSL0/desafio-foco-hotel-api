<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Hotel> */
class HotelFactory extends Factory
{
    protected $model = Hotel::class;

    public function definition(): array
    {
        return [
            'name' => 'Hotel '.fake()->unique()->lastName(),
            'external_code' => null,
            'service_fee_percent' => 0,
        ];
    }

    public function withServiceFee(float $percent): static
    {
        return $this->state(['service_fee_percent' => $percent]);
    }
}
