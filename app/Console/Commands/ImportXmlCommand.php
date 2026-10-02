<?php

namespace App\Console\Commands;

use App\Services\Import\XmlImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportXmlCommand extends Command
{
    protected $signature = 'import:xml
                            {--path= : Diretório com hotels.xml, rooms.xml e reserves.xml (padrão: config hotel.import.path)}';

    protected $description = 'Importa hotéis, quartos e reservas a partir dos arquivos XML (executado via CRON pelo scheduler)';

    public function handle(XmlImportService $service): int
    {
        $this->components->info('Iniciando importação de XML...');

        try {
            $report = $service->run($this->option('path') ?: null);
        } catch (Throwable $e) {
            $this->components->error('Falha na importação: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Entidade', 'Criados', 'Atualizados', 'Inalterados', 'Ignorados'], $report->rows());

        foreach ($report->warnings() as $warning) {
            $this->warn("  ! {$warning}");
        }

        $this->components->info('Importação concluída.');

        return self::SUCCESS;
    }
}
