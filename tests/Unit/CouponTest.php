<?php

namespace Tests\Unit;

use App\Enums\DiscountType;
use App\Models\Coupon;
use Tests\TestCase;

class CouponTest extends TestCase
{
    public function test_percent_discount(): void
    {
        $coupon = new Coupon(['type' => DiscountType::Percent, 'value' => 15]);

        $this->assertSame(4500, $coupon->discountFor(30000));
    }

    public function test_fixed_discount_is_capped_by_base(): void
    {
        $coupon = new Coupon(['type' => DiscountType::Fixed, 'value' => 50]);

        $this->assertSame(5000, $coupon->discountFor(30000));
        $this->assertSame(2000, $coupon->discountFor(2000));
    }

    public function test_validity_rules(): void
    {
        $base = ['type' => DiscountType::Percent, 'value' => 10, 'active' => true, 'used_count' => 0];

        $this->assertTrue((new Coupon($base))->isValidFor(1));
        $this->assertFalse((new Coupon([...$base, 'active' => false]))->isValidFor(1));
        $this->assertFalse((new Coupon([...$base, 'hotel_id' => 2]))->isValidFor(1));
        $this->assertTrue((new Coupon([...$base, 'hotel_id' => 1]))->isValidFor(1));
        $this->assertFalse((new Coupon([...$base, 'valid_until' => now()->subDay()->toDateString()]))->isValidFor(1));
        $this->assertFalse((new Coupon([...$base, 'valid_from' => now()->addDay()->toDateString()]))->isValidFor(1));
        $this->assertFalse((new Coupon([...$base, 'max_uses' => 5, 'used_count' => 5]))->isValidFor(1));
    }
}
