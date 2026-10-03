<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\ReserveStatus;
use App\Exceptions\ReserveCancelledException;
use App\Models\Payment;
use App\Models\Reserve;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class PaymentService
{
    /**
     * @param  array{method: int|string, value: int|float|string, installments?: int|string|null}  $data
     */
    public function register(Reserve $reserve, array $data, string $errorPrefix = ''): Payment
    {
        return DB::transaction(function () use ($reserve, $data, $errorPrefix) {
            // Lock na reserva: pagamentos simultâneos são processados um por vez, então o
            // saldo lido abaixo é sempre o atual (sem isso, dois pagamentos podiam ultrapassar o total).
            $locked = Reserve::query()->lockForUpdate()->findOrFail($reserve->id);
            $reserve->setRawAttributes($locked->getAttributes(), true);
            $reserve->unsetRelation('payments');

            if ($reserve->status === ReserveStatus::Cancelled) {
                throw new ReserveCancelledException;
            }

            $method = PaymentMethod::from((int) $data['method']);
            $value = Money::toCents($data['value']);
            $installments = (int) ($data['installments'] ?? 1);
            $balance = Money::toCents($reserve->balance());

            if ($value > $balance) {
                throw ValidationException::withMessages([
                    $errorPrefix.'value' => 'O valor excede o saldo devedor da reserva (R$ '.number_format(Money::fromCents($balance), 2, ',', '.').').',
                ]);
            }

            if ($installments > 1 && ! $method->allowsInstallments()) {
                throw ValidationException::withMessages([
                    $errorPrefix.'installments' => "Parcelamento não disponível para {$method->label()}.",
                ]);
            }

            $payment = $reserve->payments()->create([
                'method' => $method,
                'value' => Money::fromCents($value),
                'installments' => $installments,
                'interest' => Money::fromCents($this->interestFor($value, $installments)),
                'source' => 'api',
                'paid_at' => now(),
            ]);

            $reserve->refreshStatus();

            Log::info('payment.registered', [
                'reserve_id' => $reserve->id,
                'payment_id' => $payment->id,
                'method' => $method->name,
                'value' => $payment->value,
                'installments' => $installments,
            ]);

            return $payment;
        });
    }

    /** Juros simples por parcela excedente às parcelas sem juros. */
    public function interestFor(int $valueCents, int $installments): int
    {
        $free = (int) config('hotel.payments.interest_free_installments', 3);
        $rate = (float) config('hotel.payments.monthly_interest_percent', 1.99);

        if ($installments <= $free) {
            return 0;
        }

        return Money::percentOf($valueCents, $rate * ($installments - $free));
    }
}
