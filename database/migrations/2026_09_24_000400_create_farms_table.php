<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('location')->nullable();
            $table->string('municipality', 100);
            $table->string('department', 60);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('area_ha', 10, 2)->nullable();
            $table->string('timezone', 64)->default('America/Bogota');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['producer_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
