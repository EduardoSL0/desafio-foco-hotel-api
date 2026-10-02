<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Room;
use App\Services\Pricing\PriceCalculator;
use Carbon\CarbonImmutable;

/**
 * Busca do motor de reservas: dado um período e número de hóspedes, retorna os
 * quartos com unidades livres e o preço final já calculado (promoções, cupom e
 * taxas), ordenados do mais barato para o mais caro.
 */
final class AvailabilitySearchService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PriceCalculator $calculator,
    ) {}

    /** @return list<array<string, mixed>> */
    public function search(CarbonImmutable $checkIn, CarbonImmutable $checkOut, int $guests = 1, ?int $hotelId = null, ?string $couponCode = null): array
    {
        $rooms = Room::query()
            ->with('hotel')
            ->whereNotNull('daily_price')
            ->where('capacity', '>=', $guests)
            ->when($hotelId, fn ($q) => $q->where('hotel_id', $hotelId))
            ->get();

        $coupon = $couponCode
            ? Coupon::query()->where('code', strtoupper(trim($couponCode)))->first()
            : null;

        $results = [];

        foreach ($rooms as $room) {
            $available = $this->availability->availableUnits($room, $checkIn, $checkOut);

            if ($available === 0) {
                continue;
            }

            // Cupom inválido para o hotel é simplesmente ignorado na busca (não bloqueia o resultado).
            $applicable = $coupon?->isValidFor($room->hotel_id) ? $coupon : null;
            $quote = $this->calculator->quote($room, $checkIn, $checkOut, $applicable)->toArray();

            $results[] = [
                'room' => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'capacity' => $room->capacity,
                    'daily_price' => (float) $room->daily_price,
                ],
                'hotel' => ['id' => $room->hotel->id, 'name' => $room->hotel->name],
                'available_units' => $available,
                'coupon_applied' => $applicable?->code,
                'price' => [
                    'nights' => $quote['nights'],
                    'subtotal' => $quote['subtotal'],
                    'discount' => $quote['discount'],
                    'fees' => $quote['fees'],
                    'total' => $quote['total'],
                    'average_daily' => round($quote['total'] / max(1, $quote['nights']), 2),
                    'adjustments' => $quote['adjustments'],
                ],
            ];
        }

        usort($results, fn (array $a, array $b) => $a['price']['total'] <=> $b['price']['total']);

        return $results;
    }
}
