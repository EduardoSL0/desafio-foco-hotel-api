<?php

namespace App\Services\Import\Importers;

use App\Models\Hotel;
use App\Models\Room;
use App\Services\Import\Contracts\XmlEntityImporter;
use App\Services\Import\ImportReport;
use SimpleXMLElement;

final class RoomXmlImporter implements XmlEntityImporter
{
    public function key(): string
    {
        return 'rooms';
    }

    public function rootElement(): string
    {
        return 'Rooms';
    }

    public function import(SimpleXMLElement $root, ImportReport $report): void
    {
        $hotels = Hotel::query()->whereNotNull('external_code')->pluck('id', 'external_code');
        $seen = [];

        foreach ($root->Room as $node) {
            $code = trim((string) $node['id']);
            $hotelCode = trim((string) $node['hotelCode']);
            $name = trim((string) $node->Name);

            if ($code === '' || $name === '') {
                $report->skipped('rooms', 'Quarto sem atributo "id" ou sem <Name>.');

                continue;
            }

            if (mb_strlen($code) > 50 || mb_strlen($name) > 120) {
                $report->skipped('rooms', 'Quarto '.mb_strimwidth($code, 0, 20, '…').': código (máx. 50) ou nome (máx. 120 caracteres) longo demais.');

                continue;
            }

            if (isset($seen[$code])) {
                $report->skipped('rooms', "Quarto {$code}: id duplicado no arquivo; mantida a primeira ocorrência.");

                continue;
            }
            $seen[$code] = true;

            if (! $hotels->has($hotelCode)) {
                $report->skipped('rooms', "Quarto {$code}: hotel {$hotelCode} não encontrado.");

                continue;
            }

            // withTrashed: um quarto removido pelo hoteleiro não é recriado nem duplicado.
            $room = Room::withTrashed()->updateOrCreate(
                ['external_code' => $code],
                ['hotel_id' => $hotels[$hotelCode], 'name' => $name],
            );

            $report->track('rooms', $room);
        }
    }
}
