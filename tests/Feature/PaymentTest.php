<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayException;
use App\Payments\WebpayGateway;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesProfessional;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use CreatesProfessional;
    use RefreshDatabase;

    private int $serviceId;

    private FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setNowUtc('2026-12-01 12:00:00');
        config(['reservas.turnstile.secret_key' => 'secreto-de-prueba', 'webpay.environment' => 'integration']);
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);

        [, $service] = $this->makeProfessional();
        $this->serviceId = $service->id;
    }

    protected function tearDown(): void
    {
        $this->resetNow();
        parent::tearDown();
    }

    private function hold(string $start = '2026-12-07T13:00:00Z', string $email = 'ana@example.com'): Booking
    {
        $this->postJson('/manuel-erlandsen/reservar', [
            'service' => $this->serviceId,
            'start' => $start,
            'name' => 'Ana Pérez Soto',
            'document_type' => 'rut',
            'document_number' => '12.345.678-5',
            'phone' => '+56 9 1234 5678',
            'email' => $email,
            'address' => 'Calle Falsa 123, Santiago',
            'privacy' => '1',
            'adult' => '1',
            'turnstile' => 'token',
        ])->assertCreated();

        return Booking::latest('id')->first();
    }

    private function pay(Booking $booking)
    {
        return $this->post(route('payment.start', $booking->public_id));
    }

    /** Retiene, inicia el pago y entrega la reserva y el intento. */
    private function started(): array
    {
        $booking = $this->hold();
        $this->pay($booking)->assertOk();

        return [$booking, Payment::latest('id')->first()];
    }

    private function back(array $params)
    {
        return $this->get(route('payment.return', $params));
    }

    private function reconcile(): int
    {
        return $this->app->make(PaymentService::class)->reconcile();
    }

    public function test_iniciar_el_pago_extiende_la_retencion_y_envia_a_webpay(): void
    {
        $booking = $this->hold();

        $this->pay($booking)
            ->assertOk()
            ->assertSee('https://webpay.test/pagar')
            ->assertSee('name="token_ws" value="token1"', false);

        $payment = Payment::sole();
        $this->assertSame(Payment::CREATED, $payment->status);
        $this->assertSame(40000, $payment->amount);
        $this->assertSame('integration', $payment->environment);
        $this->assertLessThanOrEqual(26, strlen($payment->buy_order));
        $this->assertSame('2026-12-01 12:20:00', $booking->fresh()->hold_expires_at->format('Y-m-d H:i:s'));
    }

    public function test_otro_navegador_no_puede_pagar_la_reserva(): void
    {
        $booking = $this->hold();
        $this->flushSession();

        $this->pay($booking)->assertNotFound();
        $this->assertSame(0, Payment::count());
    }

    public function test_no_se_paga_una_reserva_vencida(): void
    {
        $booking = $this->hold();
        $this->setNowUtc('2026-12-01 12:11:00');

        $this->pay($booking)->assertRedirect(route('hold.show', $booking->public_id))
            ->assertSessionHas('payment_error');
        $this->assertSame(0, Payment::count());
    }

    public function test_limita_los_intentos_de_pago_por_reserva(): void
    {
        $booking = $this->hold();
        foreach (range(1, 3) as $i) {
            $this->pay($booking)->assertOk();
        }

        $this->pay($booking)->assertRedirect()->assertSessionHas('payment_error');
        $this->assertSame(3, Payment::count());
    }

    public function test_si_webpay_no_responde_al_crear_se_avisa(): void
    {
        $this->gateway->createFails = true;
        $booking = $this->hold();

        $this->pay($booking)->assertRedirect()->assertSessionHas('payment_error');

        $this->assertSame(Payment::ERROR, Payment::sole()->status);
        $this->assertTrue($booking->fresh()->isHeld());
    }

    public function test_pago_aprobado_confirma_la_reserva(): void
    {
        [$booking, $payment] = $this->started();
        $this->flushSession(); // el retorno no depende de la sesión

        $response = $this->back(['token_ws' => $payment->token])->assertStatus(303);

        $booking->refresh();
        $this->assertSame(Booking::CONFIRMED, $booking->status);
        $this->assertNotNull($booking->confirmed_at);
        $this->assertNull($booking->hold_expires_at);
        $this->assertSame(Payment::APPROVED, $payment->fresh()->status);
        $this->assertSame('6623', $payment->fresh()->card_last4);

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Pago aprobado')
            ->assertSee($payment->buy_order)
            ->assertSee('1213')
            ->assertDontSee('12345678-5');
    }

    public function test_el_retorno_por_post_funciona_y_no_toca_cookies(): void
    {
        [$booking, $payment] = $this->started();

        $response = $this->post(route('payment.return'), ['token_ws' => $payment->token])->assertStatus(303);

        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame(Booking::CONFIRMED, $booking->fresh()->status);
    }

    public function test_recargar_el_retorno_no_repite_la_confirmacion(): void
    {
        [, $payment] = $this->started();

        $this->back(['token_ws' => $payment->token])->assertStatus(303);
        $this->back(['token_ws' => $payment->token])->assertStatus(303);

        $this->assertCount(1, $this->gateway->commits);
        $this->assertSame(Payment::APPROVED, $payment->fresh()->status);
    }

    public function test_pago_rechazado_no_confirma_y_permite_reintentar(): void
    {
        $this->gateway->commitStatus = 'FAILED';
        $this->gateway->commitCode = -1;
        [$booking, $payment] = $this->started();

        $location = $this->back(['token_ws' => $payment->token])->headers->get('Location');

        $this->assertSame(Payment::REJECTED, $payment->fresh()->status);
        $this->assertTrue($booking->fresh()->isHeld());
        $this->get($location)->assertSee('Pago rechazado')->assertSee('Volver a intentar');
        $this->pay($booking)->assertOk();
    }

    public function test_codigo_cero_sin_autorizacion_no_confirma(): void
    {
        $this->gateway->commitStatus = 'FAILED';
        [$booking, $payment] = $this->started();

        $this->back(['token_ws' => $payment->token]);

        $this->assertSame(Booking::PENDING, $booking->fresh()->status);
    }

    public function test_monto_distinto_queda_en_revision(): void
    {
        $this->gateway->amountOverride = 100;
        [$booking, $payment] = $this->started();

        $this->back(['token_ws' => $payment->token]);

        $this->assertSame(Payment::REVIEW, $payment->fresh()->status);
        $this->assertSame(Booking::PENDING, $booking->fresh()->status);
    }

    public function test_pago_anulado_no_confirma(): void
    {
        [$booking, $payment] = $this->started();

        $location = $this->back([
            'TBK_TOKEN' => $payment->token,
            'TBK_ORDEN_COMPRA' => $payment->buy_order,
            'TBK_ID_SESION' => $booking->public_id,
        ])->assertStatus(303)->headers->get('Location');

        $this->assertSame(Payment::ABORTED, $payment->fresh()->status);
        $this->assertSame([], $this->gateway->commits);
        $this->assertTrue($booking->fresh()->isHeld());
        $this->get($location)->assertSee('Pago anulado');
    }

    public function test_error_en_el_formulario_no_confirma(): void
    {
        [, $payment] = $this->started();

        $this->back(['token_ws' => $payment->token, 'TBK_TOKEN' => $payment->token])->assertStatus(303);

        $this->assertSame(Payment::ERROR, $payment->fresh()->status);
        $this->assertSame([], $this->gateway->commits);
    }

    public function test_tiempo_agotado_exige_orden_y_sesion_correctas(): void
    {
        [$booking, $payment] = $this->started();

        $this->back(['TBK_ORDEN_COMPRA' => $payment->buy_order, 'TBK_ID_SESION' => 'otra'])->assertNotFound();
        $this->assertSame(Payment::CREATED, $payment->fresh()->status);

        $this->back(['TBK_ORDEN_COMPRA' => $payment->buy_order, 'TBK_ID_SESION' => $booking->public_id])
            ->assertStatus(303);
        $this->assertSame(Payment::TIMEOUT, $payment->fresh()->status);
    }

    public function test_retornos_desconocidos_o_vacios_dan_404(): void
    {
        $this->back(['token_ws' => 'no-existe'])->assertNotFound();
        $this->back([])->assertNotFound();
        $this->get('/pago/retorno?token_ws[]=x')->assertNotFound();
    }

    public function test_el_resultado_exige_firma(): void
    {
        [, $payment] = $this->started();

        $this->get('/pago/resultado/'.$payment->buy_order)->assertForbidden();
    }

    public function test_sin_respuesta_al_confirmar_se_resuelve_por_conciliacion(): void
    {
        $this->gateway->commitFails = true;
        [$booking, $payment] = $this->started();

        $location = $this->back(['token_ws' => $payment->token])->headers->get('Location');
        $this->assertSame(Payment::UNKNOWN, $payment->fresh()->status);
        $this->get($location)->assertSee('Estamos verificando tu pago')->assertDontSee('Volver a intentar');

        $this->gateway->commitFails = false;
        $this->setNowUtc('2026-12-01 12:02:00');
        $this->assertSame(1, $this->reconcile());

        $this->assertSame(Payment::APPROVED, $payment->fresh()->status);
        $this->assertSame(Booking::CONFIRMED, $booking->fresh()->status);
    }

    public function test_si_figura_aprobado_pero_nunca_confirma_pasa_a_revision(): void
    {
        $this->gateway->commitFails = true;
        [$booking, $payment] = $this->started();
        $this->back(['token_ws' => $payment->token]);

        $this->setNowUtc('2026-12-01 12:05:00');
        $this->reconcile();
        $this->assertSame(Payment::UNKNOWN, $payment->fresh()->status);

        $this->setNowUtc('2026-12-01 13:01:00');
        $this->reconcile();

        $this->assertSame(Payment::REVIEW, $payment->fresh()->status);
        $this->assertNotSame(Booking::CONFIRMED, $booking->fresh()->status);
    }

    public function test_concilia_un_pago_abandonado_solo_pasado_el_plazo(): void
    {
        $this->gateway->status = 'INITIALIZED';
        $this->gateway->statusCode = null;
        [, $payment] = $this->started();

        $this->setNowUtc('2026-12-01 12:10:00');
        $this->assertSame(0, $this->reconcile());

        $this->setNowUtc('2026-12-01 12:16:00');
        $this->assertSame(1, $this->reconcile());
        $this->assertSame(Payment::ABANDONED, $payment->fresh()->status);
        $this->assertSame([], $this->gateway->commits);
    }

    public function test_pagado_sin_volver_se_confirma_en_la_conciliacion(): void
    {
        [$booking, $payment] = $this->started();
        $this->setNowUtc('2026-12-01 12:16:00');

        $this->reconcile();

        $this->assertSame([$payment->token], $this->gateway->commits);
        $this->assertSame(Booking::CONFIRMED, $booking->fresh()->status);
    }

    public function test_si_la_retencion_vencio_y_la_hora_sigue_libre_se_confirma(): void
    {
        [$booking, $payment] = $this->started();
        $this->setNowUtc('2026-12-01 12:30:00');
        Booking::releaseExpiredHolds();

        $this->back(['token_ws' => $payment->token]);

        $this->assertSame(Booking::CONFIRMED, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->active_start);
    }

    public function test_si_otra_persona_tomo_la_hora_no_se_confirma_el_cobro(): void
    {
        $this->gateway->status = 'INITIALIZED';
        $this->gateway->statusCode = null;
        [$booking, $payment] = $this->started();

        $this->setNowUtc('2026-12-01 12:30:00');
        $this->flushSession();
        $other = $this->hold(email: 'otra@example.com');

        $location = $this->back(['token_ws' => $payment->token])->headers->get('Location');

        $this->assertSame(Payment::SLOT_LOST, $payment->fresh()->status);
        $this->assertSame([], $this->gateway->commits);
        $this->assertSame([], $this->gateway->refunds);
        $this->assertNotSame(Booking::CONFIRMED, $booking->fresh()->status);
        $this->assertTrue($other->fresh()->isHeld());
        $this->get($location)->assertSee('La hora ya no está disponible');
    }

    public function test_si_ademas_hubo_cargo_se_devuelve(): void
    {
        [, $payment] = $this->started();
        $this->setNowUtc('2026-12-01 12:30:00');
        $this->flushSession();
        $this->hold(email: 'otra@example.com');

        $this->back(['token_ws' => $payment->token]);

        $this->assertSame([$payment->token], $this->gateway->refunds);
        $this->assertSame(Payment::REFUNDED, $payment->fresh()->status);
    }

    public function test_un_segundo_pago_de_una_reserva_ya_pagada_no_se_cobra(): void
    {
        $this->gateway->status = 'INITIALIZED';
        $this->gateway->statusCode = null;
        [$booking, $first] = $this->started();
        $this->pay($booking)->assertOk();
        $second = Payment::latest('id')->first();

        $this->back(['token_ws' => $second->token]);
        $this->back(['token_ws' => $first->token]);

        $this->assertSame([$second->token], $this->gateway->commits);
        $this->assertSame(Payment::SLOT_LOST, $first->fresh()->status);
        $this->assertSame(Booking::CONFIRMED, $booking->fresh()->status);
    }

    public function test_cambiar_de_hora_no_suelta_una_reserva_con_pago_en_curso(): void
    {
        [$booking] = $this->started();

        $this->post(route('hold.release', $booking->public_id))->assertRedirect();
        $this->hold('2026-12-07T15:00:00Z');

        $this->assertTrue($booking->fresh()->isHeld());
        $this->assertSame('ana@example.com', $booking->fresh()->email);
        $this->get(route('hold.show', $booking->public_id))
            ->assertSee('Iniciaste un pago')
            ->assertDontSee('Cambiar de hora');
    }

    public function test_la_hora_pagada_queda_ocupada_para_siempre(): void
    {
        [$booking, $payment] = $this->started();
        $this->back(['token_ws' => $payment->token]);

        $this->setNowUtc('2026-12-02 12:00:00');
        $this->assertSame(0, Booking::releaseExpiredHolds());
        $this->assertSame(0, Booking::purgeAbandonedData());

        $slots = $this->getJson('/manuel-erlandsen/horas?service='.$this->serviceId.'&date=2026-12-07')
            ->json('slots');
        $this->assertNotContains($booking->starts_at->format('Y-m-d\TH:i:s\Z'), array_column($slots, 'start'));
    }

    public function test_produccion_sin_credenciales_no_intenta_cobrar(): void
    {
        config(['webpay.environment' => 'production', 'webpay.commerce_code' => null, 'webpay.api_key' => null]);

        $this->expectException(PaymentGatewayException::class);
        (new WebpayGateway)->create('orden', 'sesion', 1000, 'https://example.com/pago/retorno');
    }

    public function test_el_cron_programa_la_conciliacion(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('conciliar-pagos')
            ->assertSuccessful();
    }
}
