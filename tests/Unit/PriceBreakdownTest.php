<?php

namespace Tests\Unit;

use App\Enums\DiscountType;
use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\Room;
use App\Services\Pricing\PriceBreakdown;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\Rules\CouponRule;
use App\Services\Pricing\Rules\ServiceFeeRule;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class PriceBreakdownTest extends TestCase
{
    public function test_night_discount_never_exceeds_night_value(): void
    {
        $breakdown = new PriceBreakdown(new Room, CarbonImmutable::parse('2030-01-01'), CarbonImmutable::parse('2030-01-02'));
        $breakdown->addNight('2030-01-01', 10000);

        $breakdown->applyNightDiscount('2030-01-01', 15000, 'Promo');

        $this->assertSame(10000, $breakdown->nightsDiscount());
        $this->assertSame(0, $breakdown->total());
    }

    public function test_calculator_builds_one_daily_per_night_and_applies_rules_in_order(): void
    {
        $room = $this->room(dailyPrice: 200, serviceFee: 5);
        $coupon = new Coupon(['code' => 'X', 'type' => DiscountType::Fixed, 'value' => 100]);

        // Sem PromotionRule: o teste unitário não depende do banco de dados.
        $calculator = new PriceCalculator([new CouponRule, new ServiceFeeRule]);
        $breakdown = $calculator->quote($room, CarbonImmutable::parse('2030-03-10'), CarbonImmutable::parse('2030-03-14'), $coupon);

        // 4 noites * 200 = 800; cupom -100 = 700; taxa 5% = 35; total = 735
        $this->assertSame(['2030-03-10', '2030-03-11', '2030-03-12', '2030-03-13'], array_keys($breakdown->nights()));
        $this->assertSame(80000, $breakdown->subtotal());
        $this->assertSame(10000, $breakdown->discount());
        $this->assertSame(3500, $breakdown->fees());
        $this->assertSame(73500, $breakdown->total());

        $array = $breakdown->toArray();
        $this->assertSame(735.0, $array['total']);
        $this->assertCount(2, $array['adjustments']);
        $this->assertSame('Taxa de serviço (5%)', $array['adjustments'][1]['label']);
    }

    private function room(float $dailyPrice, float $serviceFee): Room
    {
        $hotel = new Hotel(['name' => 'Hotel', 'service_fee_percent' => $serviceFee]);
        $room = new Room(['name' => 'Quarto', 'daily_price' => $dailyPrice]);
        $room->setRelation('hotel', $hotel);

        return $room;
    }
}
