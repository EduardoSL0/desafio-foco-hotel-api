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

    public function rootElement(): string
    {
        return 'Hotels';
    }

    public function import(SimpleXMLElement $root, ImportReport $report): void
    {
        $seen = [];

        foreach ($root->Hotel as $node) {
            $code = trim((string) $node['id']);
            $name = trim((string) $node->Name);

            if ($code === '' || $name === '') {
                $report->skipped('hotels', 'Hotel sem atributo "id" ou sem <Name>.');

                continue;
            }

            // Registros inválidos são ignorados com aviso, sem derrubar a importação inteira.
            if (mb_strlen($code) > 50 || mb_strlen($name) > 150) {
                $report->skipped('hotels', "Hotel {$this->short($code)}: código (máx. 50) ou nome (máx. 150 caracteres) longo demais.");

                continue;
            }

            if (isset($seen[$code])) {
                $report->skipped('hotels', "Hotel {$code}: id duplicado no arquivo; mantida a primeira ocorrência.");

                continue;
            }
            $seen[$code] = true;

            $hotel = Hotel::updateOrCreate(['external_code' => $code], ['name' => $name]);

            $report->track('hotels', $hotel);
        }
    }

    private function short(string $value): string
    {
        return mb_strimwidth($value, 0, 20, '…');
    }
}
