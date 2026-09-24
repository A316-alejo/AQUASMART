<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mortalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('mortality_cause_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('recorded_on');
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mortalities');
    }
};
