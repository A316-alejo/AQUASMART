<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ponds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            // Texto validado por App\Enums\PondType: agregar un tipo nuevo no requiere migración.
            $table->string('type', 30)->index();
            $table->decimal('surface_m2', 10, 2)->nullable();
            $table->decimal('volume_m3', 10, 2)->nullable();
            $table->decimal('depth_m', 5, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['farm_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ponds');
    }
};
