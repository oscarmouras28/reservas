<?php

namespace Database\Seeders;

use App\Models\AvailabilityRule;
use App\Models\Professional;
use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Datos del prototipo de la Etapa 1: un solo psicólogo.
 * El RUT del profesional se completa aparte (no se versiona en el código).
 */
class ReservasSeeder extends Seeder
{
    public function run(): void
    {
        $professional = Professional::updateOrCreate(
            ['slug' => 'manuel-erlandsen'],
            [
                'name' => 'Manuel Erlandsen Muscio',
                'title' => 'Psicólogo',
                'timezone' => 'America/Santiago',
                'active' => true,
            ]
        );

        Service::updateOrCreate(
            ['professional_id' => $professional->id, 'kind' => Service::INDIVIDUAL],
            ['price' => 40000, 'duration_minutes' => 60, 'active' => true]
        );

        // Lunes a viernes, 09:00 a 18:00 (hora de Chile).
        foreach (range(1, 5) as $weekday) {
            AvailabilityRule::updateOrCreate(
                ['professional_id' => $professional->id, 'weekday' => $weekday],
                ['start_time' => '09:00:00', 'end_time' => '18:00:00']
            );
        }
    }
}
