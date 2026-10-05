<?php

namespace App\Console\Commands;

use App\Services\ReserveService;
use Illuminate\Console\Command;

/** Cancela pré-reservas online que não receberam pagamento dentro do prazo (executado pelo scheduler). */
class ExpirePendingReservesCommand extends Command
{
    protected $signature = 'reserves:expire';

    protected $description = 'Cancela pré-reservas sem pagamento cujo prazo venceu e libera o quarto';

    public function handle(ReserveService $reserves): int
    {
        $count = $reserves->expirePending();

        $this->components->info("Pré-reservas expiradas: {$count}.");

        return self::SUCCESS;
    }
}
