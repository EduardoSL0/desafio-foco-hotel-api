<?php

namespace App\Services\Import\Importers;

use App\Enums\PaymentMethod;
use App\Enums\ReserveStatus;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reserve;
use App\Models\Room;
use App\Services\Import\Contracts\XmlEntityImporter;
use App\Services\Import\ImportReport;
use App\Support\Money;
use Carbon\CarbonImmutable;
use SimpleXMLElement;

final class ReserveXmlImporter implements XmlEntityImporter
{
    public function key(): string
    {
        return 'reserves';
    }

    public function rootElement(): string
    {
        return 'Reserves';
    }

    public function import(SimpleXMLElement $root, ImportReport $report): void
    {
        $hotels = Hotel::query()->whereNotNull('external_code')->pluck('id', 'external_code');
        $rooms = Room::withTrashed()->whereNotNull('external_code')->get()->keyBy('external_code');
        $seen = [];

        foreach ($root->Reserve as $node) {
            $code = trim((string) $node['id']);
            $hotelCode = trim((string) $node['hotelCode']);
            $roomCode = trim((string) $node['roomCode']);
            $label = 'Reserva '.mb_strimwidth($code, 0, 20, '…');

            if ($code === '') {
                $report->skipped('reserves', 'Reserva sem atributo "id".');

                continue;
            }

            if (mb_strlen($code) > 50) {
                $report->skipped('reserves', "{$label}: código longo demais (máx. 50 caracteres).");

                continue;
            }

            if (isset($seen[$code])) {
                $report->skipped('reserves', "{$label}: id duplicado no arquivo; mantida a primeira ocorrência.");

                continue;
            }
            $seen[$code] = true;

            $hotelId = isset($hotels[$hotelCode]) ? (int) $hotels[$hotelCode] : null;
            /** @var Room|null $room */
            $room = $rooms[$roomCode] ?? null;

            if ($hotelId === null || $room === null) {
                $report->skipped('reserves', "{$label}: hotel {$hotelCode} ou quarto {$roomCode} não encontrado.");

                continue;
            }

            if ($room->hotel_id !== $hotelId) {
                $report->skipped('reserves', "{$label}: quarto {$roomCode} não pertence ao hotel {$hotelCode}.");

                continue;
            }

            $checkIn = $this->date((string) $node->CheckIn);
            $checkOut = $this->date((string) $node->CheckOut);

            if (! $checkIn || ! $checkOut || $checkOut->lte($checkIn)) {
                $report->skipped('reserves', "{$label}: período de check-in/check-out inválido.");

                continue;
            }

            $dailies = $this->dailies($node, $checkIn, $checkOut, $label, $report);
            $subtotal = array_sum(array_column($dailies, 'cents'));
            $total = Money::toCents((string) $node->Total);

            if ($total <= 0) {
                $total = $subtotal;
                $report->warning("{$label}: <Total> ausente ou inválido, usado o somatório das diárias.");
            }

            // Valores acima do suportado pelo banco: ignora só esta reserva, não a importação inteira.
            if ($total > Money::MAX_CENTS || $subtotal > Money::MAX_CENTS) {
                $report->skipped('reserves', "{$label}: valor acima do máximo suportado (R$ 99.999.999,99).");

                continue;
            }

            if ($dailies !== [] && $subtotal !== $total) {
                $report->warning(sprintf(
                    '%s: total informado (%.2f) difere da soma das diárias (%.2f); diferença registrada como desconto/taxa.',
                    $label, Money::fromCents($total), Money::fromCents($subtotal),
                ));
            }

            $reserve = Reserve::firstOrNew(['external_code' => $code]);
            $reserve->fill([
                'hotel_id' => $hotelId,
                'room_id' => $room->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'subtotal' => Money::fromCents($subtotal),
                'discount' => Money::fromCents(max(0, $subtotal - $total)),
                'fees' => Money::fromCents(max(0, $total - $subtotal)),
                'total' => Money::fromCents($total),
            ]);

            if (! $reserve->exists) {
                $reserve->status = ReserveStatus::Pending;
                $reserve->source = 'xml';
            }

            $reserve->save();
            $report->track('reserves', $reserve);

            $reserve->dailies()->delete();
            $reserve->dailies()->createMany(array_map(
                fn (array $d) => ['date' => $d['date'], 'value' => Money::fromCents($d['cents']), 'discount' => 0],
                $dailies,
            ));

            $reserve->guests()->sync($this->guests($node, $label, $report));
            $this->payments($reserve, $node, $label, $report);
            $reserve->refreshStatus();

            $this->backfillRoomRate($room, $dailies);
        }
    }

    /** @return list<array{date: string, cents: int}> */
    private function dailies(SimpleXMLElement $node, CarbonImmutable $in, CarbonImmutable $out, string $label, ImportReport $report): array
    {
        $dailies = [];

        foreach ($node->xpath('Dailies/Daily') ?: [] as $daily) {
            $date = $this->date((string) $daily->Date);

            if (! $date) {
                $report->warning("{$label}: diária com data inválida ignorada.");

                continue;
            }

            if ($date->lt($in) || $date->gte($out)) {
                $report->warning("{$label}: diária {$date->toDateString()} fora do período {$in->toDateString()} a {$out->toDateString()}.");
            }

            $cents = Money::toCents((string) $daily->Value);

            if ($cents < 0 || $cents > Money::MAX_CENTS) {
                $report->warning("{$label}: diária {$date->toDateString()} com valor inválido ignorada.");

                continue;
            }

            $dailies[] = ['date' => $date->toDateString(), 'cents' => $cents];
        }

        return $dailies;
    }

    /** @return list<int> */
    private function guests(SimpleXMLElement $node, string $label, ImportReport $report): array
    {
        $ids = [];

        foreach ($node->xpath('Guests/Guest') ?: [] as $guest) {
            $name = trim((string) $guest->Name);
            $lastName = trim((string) $guest->LastName);
            $phone = preg_replace('/\D/', '', (string) $guest->Phone);

            if ($name === '') {
                $report->warning("{$label}: hóspede sem nome ignorado.");

                continue;
            }

            if (mb_strlen($name) > 100 || mb_strlen($lastName) > 100 || strlen($phone) > 20) {
                $report->warning("{$label}: hóspede com nome, sobrenome ou telefone longo demais ignorado.");

                continue;
            }

            // Hóspedes são deduplicados por nome + sobrenome + telefone.
            $ids[] = Guest::firstOrCreate(['name' => $name, 'last_name' => $lastName, 'phone' => $phone])->id;
        }

        return array_values(array_unique($ids));
    }

    private function payments(Reserve $reserve, SimpleXMLElement $node, string $label, ImportReport $report): void
    {
        // Pagamentos registrados via API são preservados; apenas os vindos do XML são substituídos.
        $reserve->payments()->where('source', 'xml')->delete();

        foreach ($node->xpath('Payments/Payment') ?: [] as $payment) {
            $method = PaymentMethod::tryFrom((int) $payment->Method);
            $value = Money::toCents((string) $payment->Value);

            if (! $method || $value <= 0 || $value > Money::MAX_CENTS) {
                $report->warning("{$label}: pagamento com método ou valor inválido ignorado.");

                continue;
            }

            $reserve->payments()->create([
                'method' => $method,
                'value' => Money::fromCents($value),
                'installments' => 1,
                'interest' => 0,
                'source' => 'xml',
            ]);
        }
    }

    /** O XML de quartos não traz tarifa; usamos a última diária praticada como tarifa base inicial. */
    private function backfillRoomRate(Room $room, array $dailies): void
    {
        if ($room->daily_price !== null || $dailies === []) {
            return;
        }

        $room->daily_price = Money::fromCents(end($dailies)['cents']);
        $room->save();
    }

    private function date(string $value): ?CarbonImmutable
    {
        $value = trim($value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
