<?php

namespace App\Http\Controllers;

use App\Models\Professional;
use App\Models\Service;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingPageController extends Controller
{
    public function show(string $slug): View
    {
        $professional = $this->professional($slug);
        $services = $professional->services()->where('active', true)->orderBy('kind')->get();
        abort_if($services->isEmpty(), 404);

        $now = CarbonImmutable::now($professional->timezone);

        return view('booking.show', [
            'professional' => $professional,
            'config' => [
                'professional' => ['name' => $professional->name, 'title' => $professional->title],
                'services' => $services->map(fn (Service $s) => [
                    'id' => $s->id,
                    'label' => $s->label(),
                    'price' => $s->formattedPrice(),
                    'minutes' => $s->duration_minutes,
                ])->values(),
                'urls' => [
                    'days' => route('booking.days', $professional->slug),
                    'slots' => route('booking.slots', $professional->slug),
                ],
                'timezone' => $professional->timezone,
                'today' => $now->format('Y-m-d'),
                'maxMonth' => $now->addDays((int) config('reservas.max_advance_days'))->format('Y-m'),
            ],
        ]);
    }

    public function days(Request $request, string $slug, AvailabilityService $availability): JsonResponse
    {
        $data = $request->validate([
            'service' => ['required', 'integer'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $professional = $this->professional($slug);
        $service = $this->service($professional, (int) $data['service']);

        return response()->json([
            'month' => $data['month'],
            'days' => $availability->availableDays($professional, $service, $data['month']),
        ]);
    }

    public function slots(Request $request, string $slug, AvailabilityService $availability): JsonResponse
    {
        $data = $request->validate([
            'service' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $professional = $this->professional($slug);
        $service = $this->service($professional, (int) $data['service']);

        return response()->json([
            'date' => $data['date'],
            'slots' => $availability->slotsForDate($professional, $service, $data['date']),
        ]);
    }

    private function professional(string $slug): Professional
    {
        return Professional::query()
            ->where('slug', $slug)
            ->where('active', true)
            ->with('availabilityRules')
            ->firstOrFail();
    }

    private function service(Professional $professional, int $id): Service
    {
        return $professional->services()->where('active', true)->findOrFail($id);
    }
}
