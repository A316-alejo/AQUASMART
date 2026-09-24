<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('brand', 100)->nullable();
            $table->decimal('protein_pct', 5, 2);
            $table->decimal('pellet_mm', 5, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['name', 'brand']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_types');
    }
};
