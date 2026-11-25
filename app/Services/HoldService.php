<?php

namespace App\Services;

use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Professional;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Retiene una hora mientras el paciente paga. */
class HoldService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  array<string, string>  $patient  patient_name, document_type, document_number, phone, email, address
     * @param  array<int, string>  $previousHolds  public_id de retenciones anteriores de este mismo visitante
     *
     * @throws SlotUnavailableException
     */
    public function create(
        Professional $professional,
        Service $service,
        CarbonImmutable $startsAt,
        array $patient,
        array $previousHolds = [],
    ): Booking {
        $startsAt = $startsAt->utc();

        try {
            return DB::transaction(function () use ($professional, $service, $startsAt, $patient, $previousHolds) {
                // Un candado por profesional: dos reservas simultáneas se atienden en fila.
                // Debe ser la primera lectura de la transacción.
                Professional::query()->whereKey($professional->id)->lockForUpdate()->first();

                Booking::releaseExpiredHolds();

                // Si el visitante ya tenía una hora retenida, la suelta. Si la nueva
                // falla, la transacción se revierte y conserva la anterior.
                if ($previousHolds !== []) {
                    Booking::query()
                        ->whereIn('public_id', $previousHolds)
                        ->where('status', Booking::PENDING)
                        ->get()
                        ->each
                        ->cancelHold();
                }

                if (! $this->availability->isSlotAvailable($professional, $service, $startsAt)) {
                    throw new SlotUnavailableException;
                }

                return Booking::create($patient + [
                    'public_id' => (string) Str::ulid(),
                    'professional_id' => $professional->id,
                    'service_id' => $service->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->addMinutes($service->duration_minutes),
                    'status' => Booking::PENDING,
                    'hold_expires_at' => now()->addMinutes((int) config('reservas.hold_minutes')),
                    'active_start' => $startsAt,
                    'price' => $service->price,
                    'consent_accepted_at' => now(),
                    'consent_version' => config('reservas.consent_version'),
                    'consent_text' => config('reservas.consent_text'),
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new SlotUnavailableException;
        }
    }
}
