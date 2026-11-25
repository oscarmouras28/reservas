<?php

namespace Tests\Support;

use App\Models\AvailabilityRule;
use App\Models\Professional;
use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

trait CreatesProfessional
{
    /** Congela el reloj (Carbon y CarbonImmutable) en una fecha UTC. */
    protected function setNowUtc(string $utc): void
    {
        $now = CarbonImmutable::parse($utc, 'UTC');
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
    }

    protected function resetNow(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    /** Profesional de prueba: lunes a viernes 09:00-18:00, sesión de 60 min a $40.000. */
    protected function makeProfessional(string $slug = 'manuel-erlandsen'): array
    {
        // Idempotente: varias llamadas en un mismo test devuelven el mismo profesional.
        if ($existing = Professional::where('slug', $slug)->first()) {
            return [$existing->load('availabilityRules'), $existing->services()->first()];
        }

        $professional = Professional::create([
            'slug' => $slug,
            'name' => 'Manuel Erlandsen Muscio',
            'title' => 'Psicólogo',
            'timezone' => 'America/Santiago',
            'active' => true,
        ]);

        $service = Service::create([
            'professional_id' => $professional->id,
            'kind' => Service::INDIVIDUAL,
            'price' => 40000,
            'duration_minutes' => 60,
            'active' => true,
        ]);

        foreach (range(1, 5) as $weekday) {
            AvailabilityRule::create([
                'professional_id' => $professional->id,
                'weekday' => $weekday,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
            ]);
        }

        return [$professional->fresh(), $service];
    }
}
