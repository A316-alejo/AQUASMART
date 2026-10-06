<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada recovery code nunca se guarda en texto plano: code_hash es su hash (Hash::make).
        // Un usuario tiene varios códigos (un set); cada uno se invalida (used_at) por separado al usarse.
        Schema::create('account_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('code_hash');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_recovery_codes');
    }
};
