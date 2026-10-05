<?php

namespace App\Services\Pricing;

use App\Models\Coupon;
use App\Models\Room;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Resultado (mutável durante o cálculo) da cotação de uma estadia.
 * Todos os valores internos estão em centavos.
 */
final class PriceBreakdown
{
    /** @var array<string, array{value: int, discount: int}> */
    private array $nights = [];

    private int $couponDiscount = 0;

    private int $fees = 0;

    /** @var array<string, array{type: string, label: string, amount: int}> */
    private array $adjustments = [];

    public function __construct(
        public readonly Room $room,
        public readonly CarbonImmutable $checkIn,
        public readonly CarbonImmutable $checkOut,
        public readonly ?Coupon $coupon = null,
    ) {}

    public function addNight(string $date, int $value): void
    {
        $this->nights[$date] = ['value' => $value, 'discount' => 0];
    }

    /** @return array<string, array{value: int, discount: int}> */
    public function nights(): array
    {
        return $this->nights;
    }

    /**
     * Diárias com o desconto total (promoção + parte proporcional do cupom). A soma dos
     * valores líquidos é igual a subtotal - desconto, o que mantém o relatório (receita,
     * ADR, RevPAR) coerente com o total da reserva.
     *
     * @return array<string, array{value: int, discount: int}>
     */
    public function nightsWithCoupon(): array
    {
        $shares = Money::allocate(
            $this->couponDiscount,
            array_map(fn (array $n) => $n['value'] - $n['discount'], $this->nights),
        );

        $nights = $this->nights;
        foreach ($nights as $date => $night) {
            $nights[$date]['discount'] += $shares[$date];
        }

        return $nights;
    }

    public function applyNightDiscount(string $date, int $amount, string $label): void
    {
        $night = $this->nights[$date];
        $amount = max(0, min($amount, $night['value'] - $night['discount']));

        $this->nights[$date]['discount'] += $amount;
        $this->adjust('discount', $label, $amount);
    }

    public function applyCouponDiscount(int $amount, string $label): void
    {
        $amount = max(0, min($amount, $this->netNights()));

        $this->couponDiscount = $amount;
        $this->adjust('discount', $label, $amount);
    }

    public function addFee(int $amount, string $label): void
    {
        $this->fees += $amount;
        $this->adjust('fee', $label, $amount);
    }

    public function subtotal(): int
    {
        return array_sum(array_column($this->nights, 'value'));
    }

    public function nightsDiscount(): int
    {
        return array_sum(array_column($this->nights, 'discount'));
    }

    /** Valor das diárias já com descontos promocionais. */
    public function netNights(): int
    {
        return $this->subtotal() - $this->nightsDiscount();
    }

    public function couponDiscount(): int
    {
        return $this->couponDiscount;
    }

    public function discount(): int
    {
        return $this->nightsDiscount() + $this->couponDiscount;
    }

    public function fees(): int
    {
        return $this->fees;
    }

    public function total(): int
    {
        return max(0, $this->subtotal() - $this->discount() + $this->fees);
    }

    public function toArray(): array
    {
        return [
            'room_id' => $this->room->id,
            'check_in' => $this->checkIn->toDateString(),
            'check_out' => $this->checkOut->toDateString(),
            'nights' => count($this->nights),
            'coupon_code' => $this->coupon?->code,
            'dailies' => array_map(
                fn (string $date, array $night) => [
                    'date' => $date,
                    'value' => Money::fromCents($night['value']),
                    'discount' => Money::fromCents($night['discount']),
                ],
                array_keys($this->nights),
                $this->nightsWithCoupon(),
            ),
            'adjustments' => array_values(array_map(
                fn (array $a) => [...$a, 'amount' => Money::fromCents($a['amount'])],
                array_filter($this->adjustments, fn (array $a) => $a['amount'] > 0),
            )),
            'subtotal' => Money::fromCents($this->subtotal()),
            'discount' => Money::fromCents($this->discount()),
            'fees' => Money::fromCents($this->fees()),
            'total' => Money::fromCents($this->total()),
        ];
    }

    private function adjust(string $type, string $label, int $amount): void
    {
        $key = $type.'|'.$label;
        $this->adjustments[$key] ??= ['type' => $type, 'label' => $label, 'amount' => 0];
        $this->adjustments[$key]['amount'] += $amount;
    }
}
