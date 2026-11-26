<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un registro por intento de pago. No guarda datos personales ni la tarjeta
        // (solo sus últimos 4 dígitos).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id');
            // integration | production
            $table->string('environment', 12);
            // Orden de compra que se envía a Webpay (máximo 26 caracteres).
            $table->string('buy_order', 26);
            $table->string('token', 100)->nullable();
            $table->unsignedInteger('amount');
            $table->string('status', 20);
            $table->smallInteger('response_code')->nullable();
            $table->string('authorization_code', 10)->nullable();
            $table->string('payment_type_code', 5)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->unsignedSmallInteger('installments')->nullable();
            $table->dateTime('transaction_date')->nullable();
            $table->dateTime('committed_at')->nullable();
            $table->timestamps();

            $table->unique('buy_order', 'payments_buy_order_unique');
            $table->unique('token', 'payments_token_unique');
            $table->index(['status', 'created_at'], 'payments_status_index');
            $table->foreign('booking_id', 'payments_booking_fk')->references('id')->on('bookings');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('confirmed_at')->nullable()->after('hold_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });

        Schema::dropIfExists('payments');
    }
};
