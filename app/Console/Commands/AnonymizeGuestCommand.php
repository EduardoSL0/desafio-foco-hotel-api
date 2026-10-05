<?php

namespace App\Console\Commands;

use App\Models\Guest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * LGPD: atende ao pedido de eliminação de dados do titular. Nome, sobrenome, telefone e
 * e-mail são substituídos; as reservas, diárias e pagamentos continuam existindo (obrigação
 * fiscal/contábil do hotel), mas deixam de identificar a pessoa.
 */
class AnonymizeGuestCommand extends Command
{
    protected $signature = 'guests:anonymize {ids* : IDs dos hóspedes}';

    protected $description = 'Anonimiza os dados pessoais de hóspedes (pedido de eliminação - LGPD)';

    public function handle(): int
    {
        $guests = Guest::query()->whereKey($this->argument('ids'))->get();

        if ($guests->isEmpty()) {
            $this->components->error('Nenhum hóspede encontrado com os IDs informados.');

            return self::FAILURE;
        }

        foreach ($guests as $guest) {
            $guest->update([
                'name' => 'Hóspede',
                'last_name' => "Anonimizado {$guest->id}",
                'phone' => '',
                'email' => null,
            ]);

            Log::info('guest.anonymized', ['guest_id' => $guest->id]);
        }

        $this->components->info("Hóspedes anonimizados: {$guests->count()}.");

        return self::SUCCESS;
    }
}
