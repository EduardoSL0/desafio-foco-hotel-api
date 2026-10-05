<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reserves', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('source')
                ->comment('Pré-reserva online sem pagamento: deixa de ocupar o quarto após este momento');

            $table->index(['status', 'expires_at']);
            // Listagem de reservas por hotel ordenada por check-in.
            $table->index(['hotel_id', 'check_in']);
        });
    }

    public function down(): void
    {
        // O MySQL passa a usar o índice (hotel_id, check_in) na chave estrangeira de hotel_id
        // (e descarta o índice simples): ele precisa voltar a existir antes de remover o composto.
        $needsFkIndex = ! Schema::hasIndex('reserves', 'reserves_hotel_id_foreign');

        Schema::table('reserves', function (Blueprint $table) use ($needsFkIndex) {
            if ($needsFkIndex) {
                $table->index('hotel_id', 'reserves_hotel_id_foreign');
            }
            $table->dropIndex(['hotel_id', 'check_in']);
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
