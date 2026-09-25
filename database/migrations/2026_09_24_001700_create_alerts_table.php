<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una alerta es la consecuencia persistente de una medición evaluada como advertencia o crítica
        // (ver App\Services\WaterQualityEvaluator y App\Services\AlertGenerator). Conserva su propio
        // value/severity aunque después cambien los umbrales de alert_rules o se edite la finca.
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('metric_id')->constrained()->restrictOnDelete();
            // Única: evita generar más de una alerta para la misma medición.
            $table->foreignId('water_quality_reading_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('value', 10, 3);
            $table->string('severity', 20);
            $table->string('status', 20)->default('pendiente');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index(['status', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
