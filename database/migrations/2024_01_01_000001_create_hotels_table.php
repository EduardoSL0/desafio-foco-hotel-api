<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('external_code', 50)->nullable()->unique()->comment('Código do hotel no sistema de origem (XML)');
            $table->string('name', 150);
            $table->decimal('service_fee_percent', 5, 2)->default(0)->comment('Taxa de serviço aplicada às reservas');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
