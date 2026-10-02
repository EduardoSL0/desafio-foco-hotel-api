<?php

namespace App\Services\Pricing\Rules;

use App\Services\Pricing\Contracts\PriceRule;
use App\Services\Pricing\PriceBreakdown;
use App\Support\Money;

/** Acrescenta a taxa de serviço do hotel sobre o valor já descontado. */
final class ServiceFeeRule implements PriceRule
{
    public function apply(PriceBreakdown $breakdown): void
    {
        $percent = (float) ($breakdown->room->hotel?->service_fee_percent ?? 0);

        if ($percent <= 0) {
            return;
        }

        $base = $breakdown->netNights() - $breakdown->couponDiscount();
        $label = 'Taxa de serviço ('.rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.').'%)';

        $breakdown->addFee(Money::percentOf($base, $percent), $label);
    }
}
