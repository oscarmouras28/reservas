<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesProfessional;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use CreatesProfessional;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Martes 1-dic-2026, 09:00 en Chile (UTC-3, horario de verano).
        $this->setNowUtc('2026-12-01 12:00:00');
    }

    protected function tearDown(): void
    {
        $this->resetNow();
        parent::tearDown();
    }

    private function slots(string $date): array
    {
        [$professional, $service] = $this->makeProfessional();

        return (new AvailabilityService)->slotsForDate($professional, $service, $date);
    }

    public function test_un_dia_habil_ofrece_nueve_horas_de_09_a_17(): void
    {
        $slots = $this->slots('2026-12-07'); // lunes

        $this->assertSame(
            ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'],
            array_column($slots, 'time')
        );
        // 09:00 en Chile (UTC-3) = 12:00 UTC
        $this->assertSame('2026-12-07T12:00:00Z', $slots[0]['start']);
    }

    public function test_fin_de_semana_no_tiene_horas(): void
    {
        $this->assertSame([], $this->slots('2026-12-05')); // sábado
        $this->assertSame([], $this->slots('2026-12-06')); // domingo
    }

    public function test_fecha_invalida_no_tiene_horas(): void
    {
        $this->assertSame([], $this->slots('2026-02-31'));
        $this->assertSame([], $this->slots('abc'));
    }

    public function test_respeta_la_anticipacion_minima(): void
    {
        // Hoy martes 09:00 local + 12 h = 21:00: ya no queda ninguna hora hoy.
        $this->assertSame([], $this->slots('2026-12-01'));
        // Mañana desde las 09:00 sí hay.
        $this->assertCount(9, $this->slots('2026-12-02'));
    }

    public function test_no_ofrece_dias_mas_alla_del_maximo(): void
    {
        // 45 días desde el 1-dic-2026 = 15-ene-2027 (viernes); el 18-ene ya no.
        $this->assertCount(9, $this->slots('2027-01-15'));
        $this->assertSame([], $this->slots('2027-01-18'));
    }

    public function test_una_reserva_confirmada_ocupa_su_hora(): void
    {
        [$professional, $service] = $this->makeProfessional();
        Booking::create([
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'starts_at' => '2026-12-07 13:00:00', // 10:00 Chile
            'ends_at' => '2026-12-07 14:00:00',
            'status' => Booking::CONFIRMED,
            'active_start' => '2026-12-07 13:00:00',
            'price' => 40000,
        ]);

        $times = array_column((new AvailabilityService)->slotsForDate($professional, $service, '2026-12-07'), 'time');

        $this->assertCount(8, $times);
        $this->assertNotContains('10:00', $times);
    }

    public function test_una_retencion_vigente_ocupa_y_vencida_libera(): void
    {
        [$professional, $service] = $this->makeProfessional();
        Booking::create([
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'starts_at' => '2026-12-07 13:00:00',
            'ends_at' => '2026-12-07 14:00:00',
            'status' => Booking::PENDING,
            'hold_expires_at' => '2026-12-01 12:10:00', // vence en 10 min
            'active_start' => '2026-12-07 13:00:00',
            'price' => 40000,
        ]);
        $availability = new AvailabilityService;

        $this->assertNotContains('10:00', array_column($availability->slotsForDate($professional, $service, '2026-12-07'), 'time'));

        $this->setNowUtc('2026-12-01 12:11:00'); // la retención venció
        $this->assertContains('10:00', array_column($availability->slotsForDate($professional, $service, '2026-12-07'), 'time'));
    }

    public function test_dias_disponibles_del_mes_solo_dias_habiles_futuros(): void
    {
        [$professional, $service] = $this->makeProfessional();

        $days = (new AvailabilityService)->availableDays($professional, $service, '2026-12');

        $this->assertNotContains('2026-12-01', $days); // hoy, sin horas por anticipación
        $this->assertContains('2026-12-02', $days);
        $this->assertNotContains('2026-12-05', $days); // sábado
        $this->assertCount(22, $days);
    }

    public function test_is_slot_available(): void
    {
        [$professional, $service] = $this->makeProfessional();
        $availability = new AvailabilityService;

        $this->assertTrue($availability->isSlotAvailable($professional, $service, CarbonImmutable::parse('2026-12-07 12:00:00', 'UTC')));
        // 08:00 Chile: fuera del horario base.
        $this->assertFalse($availability->isSlotAvailable($professional, $service, CarbonImmutable::parse('2026-12-07 11:00:00', 'UTC')));
        // 09:30 Chile: no calza con la grilla de 60 min.
        $this->assertFalse($availability->isSlotAvailable($professional, $service, CarbonImmutable::parse('2026-12-07 12:30:00', 'UTC')));
    }

    public function test_la_base_impide_dos_reservas_activas_en_la_misma_hora(): void
    {
        [$professional, $service] = $this->makeProfessional();
        $data = [
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'starts_at' => '2026-12-07 13:00:00',
            'ends_at' => '2026-12-07 14:00:00',
            'status' => Booking::CONFIRMED,
            'active_start' => '2026-12-07 13:00:00',
            'price' => 40000,
        ];
        Booking::create($data);

        $this->expectException(QueryException::class);
        Booking::create($data);
    }

    public function test_liberar_retenciones_vencidas_permite_reservar_de_nuevo(): void
    {
        [$professional, $service] = $this->makeProfessional();
        $base = [
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'starts_at' => '2026-12-07 13:00:00',
            'ends_at' => '2026-12-07 14:00:00',
            'price' => 40000,
        ];
        $held = Booking::create($base + [
            'status' => Booking::PENDING,
            'hold_expires_at' => '2026-12-01 12:10:00',
            'active_start' => '2026-12-07 13:00:00',
        ]);

        $this->setNowUtc('2026-12-01 12:11:00');
        $this->assertSame(1, Booking::releaseExpiredHolds());

        $held->refresh();
        $this->assertSame(Booking::EXPIRED, $held->status);
        $this->assertNull($held->active_start);

        // Ahora otra persona puede tomar la misma hora.
        $new = Booking::create($base + [
            'status' => Booking::CONFIRMED,
            'active_start' => '2026-12-07 13:00:00',
        ]);
        $this->assertTrue($new->exists);
    }
}
