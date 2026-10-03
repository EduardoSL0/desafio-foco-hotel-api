<?php

namespace App\Services\Import;

use App\Exceptions\ImportException;
use App\Services\Import\Contracts\XmlEntityImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orquestra a importação: carrega cada arquivo e delega ao importador da
 * entidade, na ordem configurada. Tudo roda em uma única transação: ou a
 * importação inteira é aplicada, ou nada é alterado.
 */
final class XmlImportService
{
    /** @param  list<XmlEntityImporter>  $importers */
    public function __construct(
        private readonly XmlLoader $loader,
        private readonly array $importers,
    ) {}

    public function run(?string $directory = null): ImportReport
    {
        $directory = rtrim($directory ?? config('hotel.import.path'), '/\\');
        $files = config('hotel.import.files');
        $report = new ImportReport;

        Log::channel('import')->info('import.started', ['directory' => $directory]);

        // Valida/carrega todos os arquivos antes de tocar no banco.
        $documents = [];
        foreach ($this->importers as $importer) {
            $file = $files[$importer->key()] ?? throw new ImportException("Arquivo não configurado para '{$importer->key()}'.");
            $path = $directory.DIRECTORY_SEPARATOR.$file;
            $document = $this->loader->load($path);

            if ($document->getName() !== $importer->rootElement()) {
                throw new ImportException("Elemento raiz inválido em {$path}: esperado <{$importer->rootElement()}>, encontrado <{$document->getName()}>.");
            }

            $documents[$importer->key()] = $document;
        }

        try {
            DB::transaction(function () use ($documents, $report) {
                foreach ($this->importers as $importer) {
                    $importer->import($documents[$importer->key()], $report);
                }
            });
        } catch (Throwable $e) {
            Log::channel('import')->error('import.failed', ['error' => $e->getMessage()]);

            throw $e;
        }

        foreach ($report->warnings() as $warning) {
            Log::channel('import')->warning($warning);
        }

        Log::channel('import')->info('import.finished', $report->toArray()['stats']);

        return $report;
    }
}
