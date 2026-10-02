<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dailies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserve_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('value', 10, 2)->comment('Valor bruto da diária');
            $table->decimal('discount', 10, 2)->default(0)->comment('Desconto promocional aplicado à diária');
            $table->timestamps();

            $table->index(['reserve_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dailies');
    }
};
