<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El token del archivo de recuperación nunca se guarda en texto plano: token_hash es su sha256,
        // igual que el token de dispositivos IoT (Device::generateToken). Una sola fila activa por usuario.
        Schema::create('account_recovery_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_recovery_files');
    }
};
