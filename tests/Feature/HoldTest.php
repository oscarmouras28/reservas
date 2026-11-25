<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesProfessional;
use Tests\TestCase;

class HoldTest extends TestCase
{
    use CreatesProfessional;
    use RefreshDatabase;

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setNowUtc('2026-12-01 12:00:00');
        config(['reservas.turnstile.secret_key' => 'secreto-de-prueba']);
        Http::preventStrayRequests();

        [, $service] = $this->makeProfessional();
        $this->serviceId = $service->id;
    }

    protected function tearDown(): void
    {
        $this->resetNow();
        parent::tearDown();
    }

    private function turnstileResponds(bool $success): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => $success])]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'service' => $this->serviceId,
            'start' => '2026-12-07T13:00:00Z', // lunes 10:00 en Chile
            'name' => 'Ana Pérez Soto',
            'document_type' => 'rut',
            'document_number' => '12.345.678-5',
            'phone' => '+56 9 1234 5678',
            'email' => 'Ana@Example.com',
            'address' => 'Calle Falsa 123, Santiago',
            'privacy' => '1',
            'adult' => '1',
            'turnstile' => 'token',
        ], $override);
    }

    private function send(array $override = [])
    {
        return $this->postJson('/manuel-erlandsen/reservar', $this->payload($override));
    }

    public function test_retiene_la_hora_y_guarda_consentimiento(): void
    {
        $this->turnstileResponds(true);

        $response = $this->send()->assertCreated();

        $booking = Booking::sole();
        $response->assertJsonPath('redirect', route('hold.show', $booking->public_id));
        $this->assertSame(Booking::PENDING, $booking->status);
        $this->assertSame('2026-12-01 12:10:00', $booking->hold_expires_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-07 14:00:00', $booking->ends_at->format('Y-m-d H:i:s'));
        $this->assertSame(40000, $booking->price);
        $this->assertSame('12345678-5', $booking->document_number);
        $this->assertSame('+56912345678', $booking->phone);
        $this->assertSame('ana@example.com', $booking->email);
        $this->assertSame(config('reservas.consent_version'), $booking->consent_version);
        $this->assertStringContainsString('somosbienestar.cl/politica-de-privacidad', $booking->consent_text);
        $this->assertNotNull($booking->consent_accepted_at);
    }

    public function test_documento_telefono_y_direccion_se_guardan_cifrados(): void
    {
        $this->turnstileResponds(true);
        $this->send()->assertCreated();

        $row = DB::table('bookings')->first();

        $this->assertStringNotContainsString('12345678', $row->document_number);
        $this->assertStringNotContainsString('912345678', $row->phone);
        $this->assertStringNotContainsString('Calle Falsa', $row->address);
    }

    public function test_rechaza_rut_invalido(): void
    {
        $this->send(['document_number' => '12.345.678-9'])
            ->assertStatus(422)->assertJsonValidationErrors('document_number');
        $this->assertSame(0, Booking::count());
    }

    public function test_acepta_pasaporte(): void
    {
        $this->turnstileResponds(true);

        $this->send(['document_type' => 'passport', 'document_number' => ' ab 123456 '])->assertCreated();

        $this->assertSame('AB123456', Booking::sole()->document_number);
    }

    public function test_exige_privacidad_y_mayoria_de_edad(): void
    {
        $this->send(['privacy' => '', 'adult' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['privacy', 'adult']);
    }

    public function test_rechaza_si_turnstile_falla(): void
    {
        $this->turnstileResponds(false);

        $this->send()->assertStatus(422)->assertJsonValidationErrors('turnstile');
        $this->assertSame(0, Booking::count());
    }

    public function test_sin_clave_secreta_se_rechaza(): void
    {
        config(['reservas.turnstile.secret_key' => null]);

        $this->send()->assertStatus(422)->assertJsonValidationErrors('turnstile');
    }

    public function test_otra_persona_no_puede_tomar_una_hora_retenida(): void
    {
        $this->turnstileResponds(true);
        $this->send()->assertCreated();

        $this->flushSession(); // otro visitante
        $this->send(['email' => 'otra@example.com'])->assertStatus(409);

        $this->assertSame(1, Booking::count());
    }

    public function test_hora_fuera_del_horario_se_rechaza(): void
    {
        $this->turnstileResponds(true);

        $this->send(['start' => '2026-12-05T13:00:00Z'])->assertStatus(409); // sábado
    }

    public function test_la_hora_vuelve_a_estar_libre_al_vencer(): void
    {
        $this->turnstileResponds(true);
        $this->send()->assertCreated();

        $this->flushSession();
        $this->setNowUtc('2026-12-01 12:11:00');
        $this->send(['email' => 'otra@example.com'])->assertCreated();

        $this->assertSame(1, Booking::where('status', Booking::EXPIRED)->count());
        $this->assertSame(1, Booking::where('status', Booking::PENDING)->count());
    }

    public function test_al_cambiar_de_hora_se_suelta_la_anterior(): void
    {
        $this->turnstileResponds(true);
        $this->send()->assertCreated();
        $this->send(['start' => '2026-12-07T15:00:00Z'])->assertCreated();

        $old = Booking::where('starts_at', '2026-12-07 13:00:00')->sole();
        $this->assertSame(Booking::CANCELLED, $old->status);
        $this->assertNull($old->active_start);
        $this->assertNull($old->patient_name);
        $this->assertSame(1, Booking::where('status', Booking::PENDING)->count());
    }

    public function test_reenviar_la_misma_hora_no_se_bloquea_a_si_mismo(): void
    {
        $this->turnstileResponds(true);
        $this->send()->assertCreated();
        $this->send()->assertCreated();

        $this->assertSame(1, Booking::where('status', Booking::PENDING)->count());
    }

    public function test_si_la_nueva_hora_falla_se_conserva_la_anterior(): void
    {
        $this->turnstileResponds(true);
        $this->send()->assertCreated();
        $this->send(['start' => '2026-12-05T13:00:00Z'])->assertStatus(409);

        $this->assertSame(Booking::PENDING, Booking::sole()->status);
    }

    public function test_solo_su_navegador_ve_la_retencion(): void
    {
        $this->turnstileResponds(true);
        $this->send()->assertCreated();
        $url = route('hold.show', Booking::sole()->public_id);

        $this->get($url)
            ->assertOk()
            ->assertSee('Tu hora está apartada')
            ->assertSee('Ana Pérez Soto')
            ->assertSee('$40.000')
            ->assertDontSee('12345678-5')
            ->assertDontSee('Calle Falsa');

        $this->flushSession();
        $this->get($url)->assertNotFound();
        $this->post(route('hold.release', Booking::sole()->public_id))->assertNotFound();
    }

    public function test_la_pagina_avisa_cuando_vence(): void
    {
        $this->turnstileResponds(true);
        $this->send()->assertCreated();
        $this->setNowUtc('2026-12-01 12:11:00');

        $this->get(route('hold.show', Booking::sole()->public_id))
            ->assertOk()->assertSee('La reserva venció');
    }

    public function test_cambiar_de_hora_libera_y_vuelve_al_calendario(): void
    {
        $this->turnstileResponds(true);
        $this->send()->assertCreated();

        $this->post(route('hold.release', Booking::sole()->public_id))
            ->assertRedirect(route('booking.show', 'manuel-erlandsen'));

        $this->assertSame(Booking::CANCELLED, Booking::sole()->status);
        $this->assertNull(Booking::sole()->email);
    }

    public function test_limita_los_intentos_de_envio(): void
    {
        foreach (range(1, 5) as $i) {
            $this->send(['privacy' => ''])->assertStatus(422);
        }

        $this->send(['privacy' => ''])->assertStatus(429);
    }

    public function test_depura_datos_de_reservas_abandonadas(): void
    {
        $this->turnstileResponds(true);
        $this->send()->assertCreated();

        $this->setNowUtc('2026-12-01 12:11:00');
        Booking::releaseExpiredHolds();
        $this->assertSame(0, Booking::purgeAbandonedData()); // aún dentro del plazo

        $this->setNowUtc('2026-12-09 12:00:00');
        $this->assertSame(1, Booking::purgeAbandonedData());

        $booking = Booking::sole();
        $this->assertNull($booking->patient_name);
        $this->assertNull($booking->document_number);
        $this->assertNull($booking->email);
    }

    public function test_el_cron_programa_las_dos_tareas(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('liberar-retenciones')
            ->assertSuccessful();
    }
}
