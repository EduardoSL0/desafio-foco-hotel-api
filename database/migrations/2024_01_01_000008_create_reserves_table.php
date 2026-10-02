<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_code', 50)->nullable()->unique()->comment('Código da reserva no sistema de origem (XML)');
            $table->date('check_in');
            $table->date('check_out');
            $table->decimal('subtotal', 10, 2)->default(0)->comment('Soma das diárias');
            $table->decimal('discount', 10, 2)->default(0)->comment('Promoções + cupom');
            $table->decimal('fees', 10, 2)->default(0)->comment('Taxas de serviço');
            $table->decimal('total', 10, 2);
            $table->string('status', 20)->default('pending')->comment('pending | partially_paid | paid | cancelled');
            $table->string('source', 20)->default('api')->comment('api | xml');
            $table->timestamps();

            $table->index(['room_id', 'check_in', 'check_out']);
            $table->index('status');
        });

        Schema::create('guest_reserve', function (Blueprint $table) {
            $table->foreignId('reserve_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();

            $table->primary(['reserve_id', 'guest_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_reserve');
        Schema::dropIfExists('reserves');
    }
};
