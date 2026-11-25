<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Professional;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Calcula las horas disponibles de un profesional.
 *
 * Disponibilidad = horario base semanal (en la zona del profesional)
 *                  - reservas que ocupan el horario
 *                  - horas más cercanas que la anticipación mínima
 *                  - días más lejanos que el máximo de días hacia adelante.
 *
 * En la Etapa 1e se sumará el "ocupado" de Google Calendar.
 */
class AvailabilityService
{
    /**
     * Horas libres de un día (fecha local del profesional, Y-m-d).
     *
     * @return array<int, array{start: string, time: string}> start en UTC (ISO 8601), time en hora local HH:MM
     */
    public function slotsForDate(Professional $professional, Service $service, string $date): array
    {
        $day = $this->parseDate($professional, $date);
        if ($day === null) {
            return [];
        }

        $busy = $this->busyIntervals($professional, $day->startOfDay(), $day->endOfDay());

        return $this->daySlots($professional, $service, $day, $busy);
    }

    /**
     * Días del mes (Y-m) que tienen al menos una hora libre.
     *
     * @return array<int, string> fechas Y-m-d
     */
    public function availableDays(Professional $professional, Service $service, string $month): array
    {
        try {
            $first = CarbonImmutable::createFromFormat('!Y-m', $month, $professional->timezone);
        } catch (Throwable) {
            return [];
        }
        if (! $first || $first->format('Y-m') !== $month) {
            return [];
        }

        $last = $first->endOfMonth();
        $busy = $this->busyIntervals($professional, $first, $last);

        $days = [];
        for ($day = $first; $day <= $last; $day = $day->addDay()) {
            if ($this->daySlots($professional, $service, $day, $busy) !== []) {
                $days[] = $day->format('Y-m-d');
            }
        }

        return $days;
    }

    /** ¿Sigue libre esta hora exacta? Se usará al crear la reserva (Etapa 1b). */
    public function isSlotAvailable(Professional $professional, Service $service, CarbonImmutable $startsAt): bool
    {
        $date = $startsAt->setTimezone($professional->timezone)->format('Y-m-d');
        $iso = $startsAt->utc()->format('Y-m-d\TH:i:s\Z');

        foreach ($this->slotsForDate($professional, $service, $date) as $slot) {
            if ($slot['start'] === $iso) {
                return true;
            }
        }

        return false;
    }

    private function parseDate(Professional $professional, string $date): ?CarbonImmutable
    {
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $professional->timezone);
        } catch (Throwable) {
            return null;
        }

        // Rechaza fechas inexistentes como 2026-02-31 (PHP las "corrige").
        return ($day && $day->format('Y-m-d') === $date) ? $day : null;
    }

    /**
     * @param  array<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $busy
     * @return array<int, array{start: string, time: string}>
     */
    private function daySlots(Professional $professional, Service $service, CarbonImmutable $day, array $busy): array
    {
        $now = CarbonImmutable::now();
        $earliest = $now->addHours((int) config('reservas.min_notice_hours'));

        // Último día que se ofrece (en la zona del profesional).
        $lastDay = $now->setTimezone($professional->timezone)
            ->startOfDay()
            ->addDays((int) config('reservas.max_advance_days'));
        if ($day->startOfDay() > $lastDay) {
            return [];
        }

        $step = max(1, $service->duration_minutes);
        $slots = [];

        $rules = $professional->availabilityRules->where('weekday', $day->dayOfWeekIso);
        foreach ($rules as $rule) {
            $end = $day->setTimeFromTimeString($rule->end_time);

            for ($start = $day->setTimeFromTimeString($rule->start_time); $start->addMinutes($step) <= $end; $start = $start->addMinutes($step)) {
                $slotEnd = $start->addMinutes($step);

                if ($start < $earliest || $this->overlaps($start, $slotEnd, $busy)) {
                    continue;
                }

                $key = $start->utc()->format('Y-m-d\TH:i:s\Z');
                $slots[$key] = ['start' => $key, 'time' => $start->format('H:i')];
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    /**
     * Reservas que ocupan horario dentro de [$from, $to].
     *
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function busyIntervals(Professional $professional, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Booking::query()
            ->where('professional_id', $professional->id)
            ->occupying()
            ->where('starts_at', '<', $to->utc())
            ->where('ends_at', '>', $from->utc())
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Booking $b) => [
                'start' => CarbonImmutable::instance($b->starts_at),
                'end' => CarbonImmutable::instance($b->ends_at),
            ])
            ->all();
    }

    /** @param array<int, array{start: CarbonImmutable, end: CarbonImmutable}> $busy */
    private function overlaps(CarbonImmutable $start, CarbonImmutable $end, array $busy): bool
    {
        foreach ($busy as $interval) {
            if ($start < $interval['end'] && $end > $interval['start']) {
                return true;
            }
        }

        return false;
    }
}
