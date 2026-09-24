<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            // Identificador estable que usarán las lecturas manuales, los sensores y la API (temperature, ph…).
            $table->string('code', 40)->unique();
            $table->string('name', 100)->unique();
            $table->string('unit', 20)->nullable();
            $table->unsignedTinyInteger('decimals')->default(2);
            // Rango físicamente posible de la medición: permite descartar lecturas imposibles (no son umbrales de alerta).
            $table->decimal('min_value', 10, 3)->nullable();
            $table->decimal('max_value', 10, 3)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
