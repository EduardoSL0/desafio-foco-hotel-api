<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Coupon> */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'hotel_id' => null,
            'code' => strtoupper(fake()->unique()->bothify('CUPOM-####')),
            'type' => DiscountType::Percent,
            'value' => 10,
            'valid_from' => null,
            'valid_until' => null,
            'max_uses' => null,
            'used_count' => 0,
            'active' => true,
        ];
    }

    public function fixed(float $value): static
    {
        return $this->state(['type' => DiscountType::Fixed, 'value' => $value]);
    }
}
