<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            // individual | pareja
            $table->string('kind', 20);
            // Precio en pesos chilenos (sin decimales).
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['professional_id', 'kind'], 'services_professional_kind_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
