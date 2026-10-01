<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El token nunca se guarda en texto plano: token_hash es el sha256 del token real,
        // que solo se muestra una vez al crear el dispositivo o al regenerarlo.
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('code', 30)->unique();
            $table->string('type', 20);
            $table->char('token_hash', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
