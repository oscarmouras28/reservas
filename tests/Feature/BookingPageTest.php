<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesProfessional;
use Tests\TestCase;

class BookingPageTest extends TestCase
{
    use CreatesProfessional;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setNowUtc('2026-12-01 12:00:00');
    }

    protected function tearDown(): void
    {
        $this->resetNow();
        parent::tearDown();
    }

    public function test_la_portada_redirige_al_sitio_principal(): void
    {
        $this->get('/')->assertRedirect('https://psicologosahora.cl');
    }

    public function test_health_check_no_es_capturado_por_el_slug(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_muestra_la_pagina_del_psicologo(): void
    {
        $this->makeProfessional();

        $this->get('/manuel-erlandsen')
            ->assertOk()
            ->assertSee('Manuel Erlandsen Muscio')
            ->assertSee('$40.000');
    }

    public function test_psicologo_inexistente_o_inactivo_da_404(): void
    {
        [$professional] = $this->makeProfessional();

        $this->get('/no-existe')->assertNotFound();

        $professional->update(['active' => false]);
        $this->get('/manuel-erlandsen')->assertNotFound();
    }

    public function test_endpoint_de_dias(): void
    {
        [, $service] = $this->makeProfessional();

        $this->getJson("/manuel-erlandsen/dias?service={$service->id}&month=2026-12")
            ->assertOk()
            ->assertJsonPath('month', '2026-12')
            ->assertJsonCount(22, 'days');
    }

    public function test_endpoint_de_horas(): void
    {
        [, $service] = $this->makeProfessional();

        $this->getJson("/manuel-erlandsen/horas?service={$service->id}&date=2026-12-07")
            ->assertOk()
            ->assertJsonCount(9, 'slots')
            ->assertJsonPath('slots.0.time', '09:00')
            ->assertJsonPath('slots.0.start', '2026-12-07T12:00:00Z');
    }

    public function test_valida_los_parametros(): void
    {
        [, $service] = $this->makeProfessional();

        $this->getJson('/manuel-erlandsen/horas?service=abc&date=hoy')->assertStatus(422);
        $this->getJson("/manuel-erlandsen/dias?service={$service->id}&month=diciembre")->assertStatus(422);
    }

    public function test_servicio_de_otro_profesional_da_404(): void
    {
        $this->makeProfessional();
        [, $otherService] = $this->makeProfessional('otro-psicologo');

        $this->getJson("/manuel-erlandsen/horas?service={$otherService->id}&date=2026-12-07")->assertNotFound();
    }
}
