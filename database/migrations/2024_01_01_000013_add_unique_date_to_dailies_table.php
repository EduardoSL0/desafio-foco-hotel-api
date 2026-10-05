<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dailies', function (Blueprint $table) {
            // Uma diária por noite: o único também atende à chave estrangeira de reserve_id,
            // por isso é criado antes de remover o índice antigo.
            $table->unique(['reserve_id', 'date']);
            $table->dropIndex(['reserve_id', 'date']);

            // Relatório por período (filtra por data antes de juntar com reserves).
            $table->index(['date', 'reserve_id']);
        });
    }

    public function down(): void
    {
        Schema::table('dailies', function (Blueprint $table) {
            $table->index(['reserve_id', 'date']);
            $table->dropIndex(['date', 'reserve_id']);
            $table->dropUnique(['reserve_id', 'date']);
        });
    }
};
