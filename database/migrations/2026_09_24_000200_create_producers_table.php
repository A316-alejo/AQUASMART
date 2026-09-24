<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producers', function (Blueprint $table) {
            $table->id();
            $table->string('person_type', 10);
            $table->string('name', 150);
            $table->string('document_type', 5);
            $table->string('document_number', 20)->unique();
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('municipality', 100);
            $table->string('department', 60);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producers');
    }
};
