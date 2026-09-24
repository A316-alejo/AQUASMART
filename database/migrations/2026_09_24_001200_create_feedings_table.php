<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('feed_type_id')->constrained()->restrictOnDelete();
            // Quién registró el evento (trazabilidad).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('fed_on');
            $table->decimal('quantity_g', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'fed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedings');
    }
};
