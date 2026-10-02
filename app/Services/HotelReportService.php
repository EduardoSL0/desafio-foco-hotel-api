<?php

namespace App\Services;

use App\Enums\ReserveStatus;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Reserve;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de desempenho usados na hotelaria para um período [from, to] (inclusive):
 *
 *  - Ocupação  = room-nights vendidas / room-nights disponíveis
 *  - ADR       = receita de hospedagem / room-nights vendidas   (diária média)
 *  - RevPAR    = receita de hospedagem / room-nights disponíveis
 *
 * "Room-night" = uma unidade de quarto ocupada por uma noite. A receita considera o
 * valor líquido das diárias (valor - desconto promocional) de reservas não canceladas.
 */
final class HotelReportService
{
    public function build(Hotel $hotel, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->startOfDay();
        $days = (int) $from->diffInDays($to) + 1;

        $inventory = (int) $hotel->rooms()->sum('inventory');
        $availableNights = $inventory * $days;

        $nights = DB::table('dailies')
            ->join('reserves', 'reserves.id', '=', 'dailies.reserve_id')
            ->where('reserves.hotel_id', $hotel->id)
            ->where('reserves.status', '!=', ReserveStatus::Cancelled->value)
            ->whereBetween('dailies.date', [$from, $to]);

        $occupiedNights = (int) (clone $nights)->count();
        $revenue = Money::toCents((clone $nights)->sum(DB::raw('dailies.value - dailies.discount')));

        $overlapping = Reserve::query()
            ->where('hotel_id', $hotel->id)
            ->where('check_in', '<=', $to)
            ->where('check_out', '>', $from);

        $byStatus = (clone $overlapping)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n);

        $activeIds = (clone $overlapping)->active()->pluck('id');
        $booked = Money::toCents(Reserve::query()->whereIn('id', $activeIds)->sum('total'));
        $paid = Money::toCents(Payment::query()->whereIn('reserve_id', $activeIds)->sum('value'));

        $topRooms = (clone $nights)
            ->join('rooms', 'rooms.id', '=', 'reserves.room_id')
            ->selectRaw('rooms.id, rooms.name, count(*) as nights')
            ->groupBy('rooms.id', 'rooms.name')
            ->orderByDesc('nights')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['room_id' => (int) $r->id, 'name' => $r->name, 'nights' => (int) $r->nights])
            ->all();

        return [
            'hotel' => ['id' => $hotel->id, 'name' => $hotel->name],
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days],
            'occupancy' => [
                'inventory_units' => $inventory,
                'available_room_nights' => $availableNights,
                'occupied_room_nights' => $occupiedNights,
                'rate_percent' => $availableNights > 0 ? round($occupiedNights / $availableNights * 100, 2) : 0.0,
            ],
            'revenue' => [
                'room_revenue' => Money::fromCents($revenue),
                'adr' => $occupiedNights > 0 ? Money::fromCents(intdiv($revenue, $occupiedNights)) : 0.0,
                'revpar' => $availableNights > 0 ? Money::fromCents(intdiv($revenue, $availableNights)) : 0.0,
            ],
            'reserves' => [
                'by_status' => array_merge(array_fill_keys(array_column(ReserveStatus::cases(), 'value'), 0), $byStatus->all()),
                'booked_amount' => Money::fromCents($booked),
                'paid_amount' => Money::fromCents($paid),
                'outstanding_amount' => Money::fromCents(max(0, $booked - $paid)),
            ],
            'top_rooms' => $topRooms,
        ];
    }
}
