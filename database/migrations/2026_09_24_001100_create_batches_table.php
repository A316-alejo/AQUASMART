<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->restrictOnDelete();
            $table->foreignId('species_id')->constrained()->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->date('stocked_at');
            $table->unsignedInteger('initial_quantity');
            $table->decimal('initial_average_weight_g', 8, 2);
            // Texto validado por App\Enums\BatchStatus (activo, cerrado, cancelado).
            $table->string('status', 20)->default('activo');
            $table->date('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['pond_id', 'status']);
            $table->index(['status', 'stocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
