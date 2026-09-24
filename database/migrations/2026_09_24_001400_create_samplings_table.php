<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Biometrías. La biomasa no se guarda: la calcula App\Services\BiomassCalculator.
        Schema::create('samplings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('sampled_on');
            $table->unsignedInteger('sample_size');
            $table->decimal('average_weight_g', 8, 2);
            $table->decimal('average_length_cm', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'sampled_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('samplings');
    }
};
