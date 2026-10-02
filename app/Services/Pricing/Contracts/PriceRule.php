<?php

namespace App\Services\Pricing\Contracts;

use App\Services\Pricing\PriceBreakdown;

/**
 * Regra de precificação (Strategy). As regras são aplicadas em sequência
 * pelo PriceCalculator, cada uma ajustando o PriceBreakdown.
 */
interface PriceRule
{
    public function apply(PriceBreakdown $breakdown): void;
}
