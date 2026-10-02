<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->comment('Nulo = promoção válida para todos os quartos do hotel')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('discount_percent', 5, 2);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['hotel_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
