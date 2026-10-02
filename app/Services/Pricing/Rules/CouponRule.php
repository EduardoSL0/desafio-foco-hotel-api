<?php

namespace App\Services\Pricing\Rules;

use App\Services\Pricing\Contracts\PriceRule;
use App\Services\Pricing\PriceBreakdown;

/** Aplica o cupom sobre o valor das diárias já com promoções. */
final class CouponRule implements PriceRule
{
    public function apply(PriceBreakdown $breakdown): void
    {
        $coupon = $breakdown->coupon;

        if ($coupon === null) {
            return;
        }

        $breakdown->applyCouponDiscount(
            $coupon->discountFor($breakdown->netNights()),
            "Cupom: {$coupon->code}",
        );
    }
}
