<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El código OTP nunca se guarda en texto plano: code_hash es su hash (Hash::make).
        // Una sola fila activa por usuario: pedir un código nuevo invalida el anterior.
        Schema::create('account_recovery_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_recovery_otps');
    }
};
