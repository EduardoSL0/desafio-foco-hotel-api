<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Popula o banco para avaliação local:
     *  1. importa os XMLs de database/xml (hotéis, quartos, reservas) - sempre, pois é idempotente;
     *  2. na primeira execução (banco sem usuários), cria usuários de teste para cada perfil
     *     e um cupom, uma promoção e uma taxa de serviço de exemplo.
     *
     * O container Docker roda o seed a cada inicialização; por isso os dados de demonstração
     * não são recriados depois, preservando senhas e configurações alteradas pelo hoteleiro.
     */
    public function run(): void
    {
        Artisan::call('import:xml');
        $this->command?->getOutput()->write(Artisan::output());

        if (User::query()->exists()) {
            $this->command?->info('Dados de demonstração já existem; mantidos sem alteração.');

            return;
        }

        $prime = Hotel::query()->where('external_code', '1')->first();
        $beach = Hotel::query()->where('external_code', '2')->first();

        // Credenciais de teste para ambiente local (documentadas no README).
        User::create([
            'email' => 'admin@foco.test',
            'name' => 'Administrador',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);

        if ($prime) {
            User::create([
                'email' => 'gerente@foco.test',
                'name' => 'Gerente Foco Prime',
                'password' => 'password',
                'role' => UserRole::Manager,
                'hotel_id' => $prime->id,
            ]);

            User::create([
                'email' => 'recepcao@foco.test',
                'name' => 'Recepção Foco Prime',
                'password' => 'password',
                'role' => UserRole::Receptionist,
                'hotel_id' => $prime->id,
            ]);

            Promotion::create([
                'hotel_id' => $prime->id,
                'name' => 'Baixa temporada',
                'discount_percent' => 15,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addDays(60)->toDateString(),
            ]);
        }

        $beach?->update(['service_fee_percent' => 10]);

        Coupon::firstOrCreate(['code' => 'BEMVINDO10'], [
            'type' => DiscountType::Percent,
            'value' => 10,
        ]);
    }
}
