<?php

namespace App\Services\Import;

use App\Exceptions\ImportException;
use SimpleXMLElement;

final class XmlLoader
{
    public function load(string $path): SimpleXMLElement
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new ImportException("Arquivo XML não encontrado ou sem permissão de leitura: {$path}");
        }

        $previous = libxml_use_internal_errors(true);

        try {
            // LIBXML_NONET impede o carregamento de recursos externos (proteção contra XXE).
            $xml = simplexml_load_file($path, SimpleXMLElement::class, LIBXML_NONET);

            if ($xml === false) {
                $errors = array_map(fn ($e) => trim($e->message), libxml_get_errors());

                throw new ImportException("XML inválido em {$path}: ".implode('; ', $errors));
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
