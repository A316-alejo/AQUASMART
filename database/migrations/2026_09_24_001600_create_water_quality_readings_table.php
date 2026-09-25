<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Misma forma para mediciones manuales y, más adelante, las que envíen los sensores (columna `source`).
        Schema::create('water_quality_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('metric_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('measured_at');
            $table->decimal('value', 10, 3);
            $table->string('source', 10)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'measured_at']);
            $table->index(['batch_id', 'metric_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_quality_readings');
    }
};
