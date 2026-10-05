<?php

namespace App\Http\Resources;

use App\Models\Daily;
use App\Models\Guest;
use App\Models\Reserve;
use Illuminate\Http\Request;

/**
 * Visão da reserva para o hóspede (rotas públicas: "minha reserva" e reserva sem login).
 *
 * Quem tem o localizador e um sobrenome não é necessariamente o titular de todos os
 * dados: por isso não há telefone/e-mail dos hóspedes, nem a lista de pagamentos
 * (só os totais pago e saldo). A visão completa fica restrita à equipe do hotel.
 *
 * @mixin Reserve
 */
class PublicReserveResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'hotel' => ['id' => $this->hotel->id, 'name' => $this->hotel->name],
            'room' => ['id' => $this->room->id, 'name' => $this->room->name],
            'coupon_code' => $this->whenLoaded('coupon', fn () => $this->coupon?->code),
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'nights' => $this->nights(),
            'amounts' => [
                'subtotal' => (float) $this->subtotal,
                'discount' => (float) $this->discount,
                'fees' => (float) $this->fees,
                'total' => (float) $this->total,
                'paid' => $this->paidAmount(),
                'balance' => $this->balance(),
            ],
            'guests' => $this->whenLoaded('guests', fn () => $this->guests
                ->map(fn (Guest $g) => ['name' => $g->name, 'last_name' => $g->last_name])
                ->values()),
            'dailies' => $this->whenLoaded('dailies', fn () => $this->dailies
                ->sortBy(fn (Daily $d) => $d->date->toDateString())
                ->values()
                ->map(fn (Daily $d) => [
                    'date' => $d->date->toDateString(),
                    'value' => (float) $d->value,
                    'discount' => (float) $d->discount,
                ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
