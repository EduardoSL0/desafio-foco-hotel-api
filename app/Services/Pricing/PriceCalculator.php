<?php

namespace App\Services\Pricing;

use App\Exceptions\RoomWithoutRateException;
use App\Models\Coupon;
use App\Models\Room;
use App\Services\Pricing\Contracts\PriceRule;
use App\Support\Money;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Monta as diárias da estadia a partir da tarifa do quarto e aplica as regras
 * de precificação na ordem em que foram registradas (ver AppServiceProvider):
 * promoções -> cupom -> taxas.
 */
final class PriceCalculator
{
    /** @param  list<PriceRule>  $rules */
    public function __construct(private readonly array $rules) {}

    public function quote(Room $room, DateTimeInterface $checkIn, DateTimeInterface $checkOut, ?Coupon $coupon = null): PriceBreakdown
    {
        if ($room->daily_price === null) {
            throw new RoomWithoutRateException;
        }

        $in = CarbonImmutable::instance($checkIn)->startOfDay();
        $out = CarbonImmutable::instance($checkOut)->startOfDay();

        $breakdown = new PriceBreakdown($room, $in, $out, $coupon);
        $rate = Money::toCents($room->daily_price);

        for ($day = $in; $day->lt($out); $day = $day->addDay()) {
            $breakdown->addNight($day->toDateString(), $rate);
        }

        foreach ($this->rules as $rule) {
            $rule->apply($breakdown);
        }

        return $breakdown;
    }
}
