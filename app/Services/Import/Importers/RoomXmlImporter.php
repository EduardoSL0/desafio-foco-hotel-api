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

    public function import(SimpleXMLElement $root, ImportReport $report): void
    {
        $hotels = Hotel::query()->whereNotNull('external_code')->pluck('id', 'external_code');

        foreach ($root->Room as $node) {
            $code = trim((string) $node['id']);
            $hotelCode = trim((string) $node['hotelCode']);
            $name = trim((string) $node->Name);

            if ($code === '' || $name === '') {
                $report->skipped('rooms', 'Quarto sem atributo "id" ou sem <Name>.');

                continue;
            }

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
