<?php

namespace App\Services;

use App\Models\Reserve;
use App\Models\Room;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Controla a disponibilidade considerando o inventário do quarto
 * (ex.: "Quarto Standard" com 10 unidades). A ocupação é calculada noite a
 * noite, e a disponibilidade do período é a da noite mais cheia.
 */
final class AvailabilityService
{
    /**
     * @param  Collection<int, Reserve>|null  $reserves  reservas ativas do quarto no período, já
     *                                                   carregadas (busca em lote); null consulta o banco.
     */
    public function availableUnits(Room $room, DateTimeInterface $checkIn, DateTimeInterface $checkOut, ?Collection $reserves = null): int
    {
        return max(0, $room->inventory - $this->peakOccupation($room, $checkIn, $checkOut, $reserves));
    }

    public function isAvailable(Room $room, DateTimeInterface $checkIn, DateTimeInterface $checkOut): bool
    {
        return $this->availableUnits($room, $checkIn, $checkOut) > 0;
    }

    /** @param  Collection<int, Reserve>|null  $reserves */
    public function peakOccupation(Room $room, DateTimeInterface $checkIn, DateTimeInterface $checkOut, ?Collection $reserves = null): int
    {
        $in = CarbonImmutable::instance($checkIn)->startOfDay();
        $out = CarbonImmutable::instance($checkOut)->startOfDay();

        $reserves ??= Reserve::query()
            ->where('room_id', $room->id)
            ->active()
            ->overlapping($in, $out)
            ->get(['id', 'room_id', 'check_in', 'check_out']);

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
