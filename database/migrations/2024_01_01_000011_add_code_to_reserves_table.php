<?php

use App\Models\Reserve;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reserves', function (Blueprint $table) {
            $table->string('code', 12)->nullable()->unique()->after('id')
                ->comment('Localizador da reserva informado ao hóspede (não sequencial)');
        });

        // Gera localizador para reservas já existentes.
        DB::table('reserves')->whereNull('code')->orderBy('id')->each(function (object $reserve) {
            DB::table('reserves')->where('id', $reserve->id)->update(['code' => Reserve::generateCode()]);
        });
    }

    public function down(): void
    {
        Schema::table('reserves', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
