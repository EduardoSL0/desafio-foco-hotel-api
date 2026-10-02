<?php

namespace App\Services\Import\Contracts;

use App\Services\Import\ImportReport;
use SimpleXMLElement;

/**
 * Importador de uma entidade específica (Strategy). Cada implementação sabe
 * qual arquivo consome e como transformar seus nós em registros do banco.
 */
interface XmlEntityImporter
{
    /** Chave do arquivo em config('hotel.import.files'). */
    public function key(): string;

    public function import(SimpleXMLElement $root, ImportReport $report): void;
}
