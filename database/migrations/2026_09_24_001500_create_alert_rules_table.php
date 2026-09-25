<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Umbrales de alerta por finca y parámetro. Distinto del rango físico válido de `metrics`
        // (min_value/max_value): eso descarta lecturas imposibles, esto clasifica lecturas válidas.
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->foreignId('metric_id')->constrained()->restrictOnDelete();
            $table->decimal('warn_min', 10, 3)->nullable();
            $table->decimal('warn_max', 10, 3)->nullable();
            $table->decimal('crit_min', 10, 3)->nullable();
            $table->decimal('crit_max', 10, 3)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['farm_id', 'metric_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
