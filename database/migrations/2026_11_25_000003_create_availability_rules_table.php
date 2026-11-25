<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            // Día de la semana ISO: 1 = lunes ... 7 = domingo.
            $table->unsignedTinyInteger('weekday');
            // Horas locales del profesional (su zona horaria).
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['professional_id', 'weekday'], 'availability_rules_prof_weekday_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_rules');
    }
};
