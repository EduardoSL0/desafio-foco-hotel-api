<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Agendamentos (CRON)
|--------------------------------------------------------------------------
|
| Basta uma única entrada no crontab do servidor:
|
|   * * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
|
| O scheduler do Laravel decide quando executar cada comando. A frequência da
| importação é configurável pela variável XML_IMPORT_CRON (padrão: de hora em hora).
|
*/

Schedule::command('import:xml')
    ->cron(config('hotel.import.schedule'))
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/import-cron.log'));
