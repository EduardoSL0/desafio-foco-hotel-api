<?php

namespace App\Services;

use App\Models\Reserve;
use App\Models\Room;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Controla a disponibilidade considerando o inventário do quarto
 * (ex.: "Quarto Standard" com 10 unidades). A ocupação é calculada noite a
 * noite, e a disponibilidade do período é a da noite mais cheia.
 */
final class AvailabilityService
{
    public function availableUnits(Room $room, DateTimeInterface $checkIn, DateTimeInterface $checkOut): int
    {
        return max(0, $room->inventory - $this->peakOccupation($room, $checkIn, $checkOut));
    }

    public function isAvailable(Room $room, DateTimeInterface $checkIn, DateTimeInterface $checkOut): bool
    {
        return $this->availableUnits($room, $checkIn, $checkOut) > 0;
    }

    public function peakOccupation(Room $room, DateTimeInterface $checkIn, DateTimeInterface $checkOut): int
    {
        $in = CarbonImmutable::instance($checkIn)->startOfDay();
        $out = CarbonImmutable::instance($checkOut)->startOfDay();

        $reserves = Reserve::query()
            ->where('room_id', $room->id)
            ->active()
            ->overlapping($in, $out)
            ->get(['id', 'check_in', 'check_out']);

        if ($reserves->isEmpty()) {
            return 0;
        }

        $peak = 0;

        for ($night = $in; $night->lt($out); $night = $night->addDay()) {
            $occupied = $reserves
                ->filter(fn (Reserve $r) => $r->check_in->lte($night) && $r->check_out->gt($night))
                ->count();

            $peak = max($peak, $occupied);
        }

        return $peak;
    }
}
