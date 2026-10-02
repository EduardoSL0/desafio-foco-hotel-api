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
     *  1. importa os XMLs de database/xml (hotéis, quartos, reservas);
     *  2. cria usuários de teste para cada perfil;
     *  3. cria um cupom, uma promoção e uma taxa de serviço de exemplo.
     */
    public function run(): void
    {
        Artisan::call('import:xml');
        $this->command?->getOutput()->write(Artisan::output());

        $prime = Hotel::query()->where('external_code', '1')->first();
        $beach = Hotel::query()->where('external_code', '2')->first();

        // Credenciais de teste para ambiente local (documentadas no README).
        User::updateOrCreate(['email' => 'admin@foco.test'], [
            'name' => 'Administrador',
            'password' => 'password',
            'role' => UserRole::Admin,
            'hotel_id' => null,
        ]);

        if ($prime) {
            User::updateOrCreate(['email' => 'gerente@foco.test'], [
                'name' => 'Gerente Foco Prime',
                'password' => 'password',
                'role' => UserRole::Manager,
                'hotel_id' => $prime->id,
            ]);

            User::updateOrCreate(['email' => 'recepcao@foco.test'], [
                'name' => 'Recepção Foco Prime',
                'password' => 'password',
                'role' => UserRole::Receptionist,
                'hotel_id' => $prime->id,
            ]);

            Promotion::updateOrCreate(['name' => 'Baixa temporada', 'hotel_id' => $prime->id], [
                'discount_percent' => 15,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addDays(60)->toDateString(),
                'active' => true,
            ]);
        }

        $beach?->update(['service_fee_percent' => 10]);

        Coupon::updateOrCreate(['code' => 'BEMVINDO10'], [
            'hotel_id' => null,
            'type' => DiscountType::Percent,
            'value' => 10,
            'active' => true,
        ]);
    }
}
