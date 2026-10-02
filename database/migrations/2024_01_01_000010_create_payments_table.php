<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserve_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('method')->comment('1 crédito | 2 débito | 3 pix | 4 dinheiro | 5 boleto');
            $table->decimal('value', 10, 2)->comment('Valor abatido do saldo da reserva');
            $table->unsignedTinyInteger('installments')->default(1);
            $table->decimal('interest', 10, 2)->default(0)->comment('Juros de parcelamento cobrados do hóspede');
            $table->string('source', 20)->default('api')->comment('api | xml');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
