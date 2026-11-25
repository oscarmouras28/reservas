<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Identificador público e impredecible para las URL de la reserva.
            $table->ulid('public_id')->nullable()->after('id');

            $table->string('patient_name', 120)->nullable()->after('price');
            // rut | passport
            $table->string('document_type', 10)->nullable()->after('patient_name');
            // Documento, teléfono y dirección se guardan cifrados (APP_KEY).
            $table->text('document_number')->nullable()->after('document_type');
            $table->text('phone')->nullable()->after('document_number');
            $table->string('email', 190)->nullable()->after('phone');
            $table->text('address')->nullable()->after('email');

            // Registro del consentimiento: cuándo, qué versión y qué texto aceptó.
            $table->dateTime('consent_accepted_at')->nullable()->after('address');
            $table->string('consent_version', 20)->nullable()->after('consent_accepted_at');
            $table->text('consent_text')->nullable()->after('consent_version');

            $table->unique('public_id', 'bookings_public_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_public_id_unique');
            $table->dropColumn([
                'public_id', 'patient_name', 'document_type', 'document_number', 'phone',
                'email', 'address', 'consent_accepted_at', 'consent_version', 'consent_text',
            ]);
        });
    }
};
