<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No se duplica el catálogo metrics: el sensor solo referencia el parámetro que mide.
        // pond_id indica a qué estanque pertenecen sus lecturas (y por tanto a qué lote activo
        // se deben asociar), porque un dispositivo puede agrupar sensores de varios estanques de la finca.
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->restrictOnDelete();
            $table->foreignId('pond_id')->constrained()->restrictOnDelete();
            $table->foreignId('metric_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('code', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['device_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};
