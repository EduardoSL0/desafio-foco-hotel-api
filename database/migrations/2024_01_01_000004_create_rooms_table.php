<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('external_code', 50)->nullable()->unique()->comment('Código do quarto no sistema de origem (XML)');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('capacity')->default(2)->comment('Máximo de hóspedes por unidade');
            $table->unsignedSmallInteger('inventory')->default(1)->comment('Quantidade de unidades disponíveis desta acomodação');
            $table->decimal('daily_price', 10, 2)->nullable()->comment('Tarifa base da diária');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
