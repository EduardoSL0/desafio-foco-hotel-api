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

// O resultado de cada execução fica em storage/logs/import-AAAA-MM-DD.log (rotação diária).
// A trava contra execuções simultâneas expira em 10 minutos: se o processo morrer no meio
// (a transação é desfeita), a próxima execução não fica bloqueada por 24h.
Schedule::command('import:xml')
    ->cron(config('hotel.import.schedule'))
    ->withoutOverlapping(10);

// Pré-reservas online sem pagamento cujo prazo venceu.
Schedule::command('reserves:expire')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

// Remove tokens de acesso vencidos há mais de 24h.
Schedule::command('sanctum:prune-expired --hours=24')
    ->daily();
