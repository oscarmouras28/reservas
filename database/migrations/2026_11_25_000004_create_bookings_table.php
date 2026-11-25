<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_id')->constrained();
            $table->foreignId('service_id')->constrained();

            // Fechas siempre en UTC; se muestran en la zona del profesional.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // pending | confirmed | cancelled | expired
            $table->string('status', 20)->default('pending');
            $table->dateTime('hold_expires_at')->nullable();

            // Igual a starts_at mientras la reserva ocupa el horario y NULL cuando
            // se libera (cancelada o expirada). El índice único impide que dos
            // reservas activas tomen la misma hora, incluso con dos navegadores
            // a la vez. NULL se permite repetido.
            $table->dateTime('active_start')->nullable();

            // Precio acordado al reservar (CLP), por si el precio cambia después.
            $table->unsignedInteger('price');
            $table->timestamps();

            $table->unique(['professional_id', 'active_start'], 'bookings_prof_active_start_unique');
            $table->index(['professional_id', 'starts_at'], 'bookings_prof_starts_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
