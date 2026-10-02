<?php

namespace App\Services\Import\Importers;

use App\Models\Hotel;
use App\Services\Import\Contracts\XmlEntityImporter;
use App\Services\Import\ImportReport;
use SimpleXMLElement;

final class HotelXmlImporter implements XmlEntityImporter
{
    public function key(): string
    {
        return 'hotels';
    }

    public function import(SimpleXMLElement $root, ImportReport $report): void
    {
        foreach ($root->Hotel as $node) {
            $code = trim((string) $node['id']);
            $name = trim((string) $node->Name);

            if ($code === '' || $name === '') {
                $report->skipped('hotels', 'Hotel sem atributo "id" ou sem <Name>.');

                continue;
            }

            $hotel = Hotel::updateOrCreate(['external_code' => $code], ['name' => $name]);

            $report->track('hotels', $hotel);
        }
    }
}
