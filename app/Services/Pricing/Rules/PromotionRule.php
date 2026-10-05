<?php

namespace App\Services\Pricing\Rules;

use App\Models\Promotion;
use App\Services\Pricing\Contracts\PriceRule;
use App\Services\Pricing\PriceBreakdown;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Aplica, em cada diária, a promoção de maior percentual vigente naquela data.
 * Promoções não são cumulativas entre si.
 */
final class PromotionRule implements PriceRule
{
    public function apply(PriceBreakdown $breakdown): void
    {
        // A busca de disponibilidade pré-carrega as promoções do período para todos os quartos
        // (relação "applicablePromotions"), evitando uma consulta por quarto.
        $promotions = $breakdown->room->relationLoaded('applicablePromotions')
            ? $breakdown->room->getRelation('applicablePromotions')
            : Promotion::query()
                ->applicableTo($breakdown->room, $breakdown->checkIn, $breakdown->checkOut->subDay())
                ->get();

        if ($promotions->isEmpty()) {
            return;
        }

        foreach ($breakdown->nights() as $date => $night) {
            $day = CarbonImmutable::parse($date);

            $best = $promotions
                ->filter(fn (Promotion $p) => $p->covers($day))
                ->sortByDesc(fn (Promotion $p) => (float) $p->discount_percent)
                ->first();

            if ($best) {
                $breakdown->applyNightDiscount(
                    $date,
                    Money::percentOf($night['value'], $best->discount_percent),
                    "Promoção: {$best->name}",
                );
            }
        }
    }
}
