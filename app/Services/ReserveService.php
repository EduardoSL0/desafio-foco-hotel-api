<?php

namespace App\Services;

use App\Enums\ReserveStatus;
use App\Exceptions\ReserveCancelledException;
use App\Exceptions\RoomUnavailableException;
use App\Mail\ReserveConfirmation;
use App\Models\Coupon;
use App\Models\Guest;
use App\Models\Reserve;
use App\Models\Room;
use App\Services\Pricing\PriceBreakdown;
use App\Services\Pricing\PriceCalculator;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ReserveService
{
    public function __construct(
        private readonly PriceCalculator $calculator,
        private readonly AvailabilityService $availability,
        private readonly PaymentService $payments,
    ) {}

    /** Cotação da estadia sem persistir nada. */
    public function quote(array $data): array
    {
        $room = Room::with('hotel')->findOrFail($data['room_id']);
        [$checkIn, $checkOut] = $this->period($data);

        $breakdown = $this->calculator->quote(
            $room,
            $checkIn,
            $checkOut,
            $this->resolveCoupon($data['coupon_code'] ?? null, $room),
        );

        return [
            ...$breakdown->toArray(),
            'available_units' => $this->availability->availableUnits($room, $checkIn, $checkOut),
        ];
    }

    public function create(array $data): Reserve
    {
        return DB::transaction(function () use ($data) {
            // Lock no quarto serializa reservas concorrentes do mesmo quarto (evita overbooking).
            $room = Room::query()->with('hotel')->lockForUpdate()->findOrFail($data['room_id']);
            [$checkIn, $checkOut] = $this->period($data);

            if (count($data['guests']) > $room->capacity) {
                throw ValidationException::withMessages([
                    'guests' => "O quarto comporta no máximo {$room->capacity} hóspede(s).",
                ]);
            }

            if (! $this->availability->isAvailable($room, $checkIn, $checkOut)) {
                throw new RoomUnavailableException;
            }

            // Lock no cupom impede que reservas simultâneas ultrapassem o limite de usos.
            $coupon = $this->resolveCoupon($data['coupon_code'] ?? null, $room, lock: true);
            $breakdown = $this->calculator->quote($room, $checkIn, $checkOut, $coupon);

            if (max($breakdown->subtotal(), $breakdown->total()) > Money::MAX_CENTS) {
                throw ValidationException::withMessages(['check_out' => 'O valor total da estadia ultrapassa o máximo suportado (R$ 99.999.999,99).']);
            }

            $reserve = $this->persist($room, $breakdown, $coupon);
            $reserve->guests()->sync($this->resolveGuests($data['guests']));

            $coupon?->increment('used_count');

            foreach (array_values($data['payments'] ?? []) as $i => $payment) {
                $this->payments->register($reserve, $payment, "payments.{$i}.");
            }

            Log::info('reserve.created', [
                'reserve_id' => $reserve->id,
                'room_id' => $room->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'total' => $reserve->total,
            ]);

            $reserve = $reserve->fresh(['hotel', 'room', 'coupon', 'guests', 'dailies', 'payments']);

            // Envia só depois do commit: se a transação falhar, o hóspede não recebe confirmação falsa.
            DB::afterCommit(fn () => $this->sendConfirmation($reserve));

            return $reserve;
        });
    }

    public function cancel(Reserve $reserve): Reserve
    {
        DB::transaction(function () use ($reserve) {
            // Lock na reserva: cancelamentos simultâneos não podem devolver o uso do cupom duas vezes.
            $locked = Reserve::query()->lockForUpdate()->findOrFail($reserve->id);
            $reserve->setRawAttributes($locked->getAttributes(), true);

            if ($reserve->status === ReserveStatus::Cancelled) {
                throw new ReserveCancelledException;
            }

            $reserve->update(['status' => ReserveStatus::Cancelled]);

            // Devolve o uso do cupom para que ele volte a ficar disponível.
            if ($reserve->coupon_id !== null) {
                Coupon::query()->whereKey($reserve->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
            }
        });

        Log::info('reserve.cancelled', ['reserve_id' => $reserve->id]);

        return $reserve;
    }

    /** Falha no envio de e-mail é registrada, mas nunca desfaz a reserva já confirmada. */
    private function sendConfirmation(Reserve $reserve): void
    {
        $email = $reserve->guests->first(fn (Guest $g) => filled($g->email))?->email;

        if ($email === null) {
            return;
        }

        try {
            Mail::to($email)->send(new ReserveConfirmation($reserve));
            Log::info('reserve.confirmation_sent', ['reserve_id' => $reserve->id]);
        } catch (Throwable $e) {
            Log::error('reserve.confirmation_failed', ['reserve_id' => $reserve->id, 'error' => $e->getMessage()]);
        }
    }

    private function persist(Room $room, PriceBreakdown $breakdown, ?Coupon $coupon): Reserve
    {
        $reserve = Reserve::create([
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
            'coupon_id' => $coupon?->id,
            'check_in' => $breakdown->checkIn,
            'check_out' => $breakdown->checkOut,
            'subtotal' => Money::fromCents($breakdown->subtotal()),
            'discount' => Money::fromCents($breakdown->discount()),
            'fees' => Money::fromCents($breakdown->fees()),
            'total' => Money::fromCents($breakdown->total()),
            'status' => ReserveStatus::Pending,
            'source' => 'api',
        ]);

        foreach ($breakdown->nights() as $date => $night) {
            $reserve->dailies()->create([
                'date' => $date,
                'value' => Money::fromCents($night['value']),
                'discount' => Money::fromCents($night['discount']),
            ]);
        }

        // Reserva com total zero (ex.: cupom de 100%) já nasce quitada.
        $reserve->refreshStatus();

        return $reserve;
    }

    /** @return list<int> */
    private function resolveGuests(array $guests): array
    {
        return collect($guests)
            ->map(fn (array $g) => Guest::firstOrCreate(
                ['name' => trim($g['name']), 'last_name' => trim($g['last_name']), 'phone' => $g['phone']],
                ['email' => $g['email'] ?? null],
            )->id)
            ->unique()
            ->values()
            ->all();
    }

    private function resolveCoupon(?string $code, Room $room, bool $lock = false): ?Coupon
    {
        if ($code === null || $code === '') {
            return null;
        }

        $coupon = Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->first();

        if (! $coupon || ! $coupon->isValidFor($room->hotel_id)) {
            throw ValidationException::withMessages(['coupon_code' => 'Cupom inválido, expirado ou não aplicável a este hotel.']);
        }

        return $coupon;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(array $data): array
    {
        return [
            CarbonImmutable::parse($data['check_in'])->startOfDay(),
            CarbonImmutable::parse($data['check_out'])->startOfDay(),
        ];
    }
}
