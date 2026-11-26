<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    /** Campos con datos personales del paciente. */
    private const PATIENT_FIELDS = [
        'patient_name', 'document_type', 'document_number', 'phone', 'email', 'address',
        'consent_accepted_at', 'consent_version', 'consent_text',
    ];

    protected $fillable = [
        'public_id', 'professional_id', 'service_id', 'starts_at', 'ends_at', 'status',
        'hold_expires_at', 'confirmed_at', 'active_start', 'price', 'patient_name', 'document_type',
        'document_number', 'phone', 'email', 'address', 'consent_accepted_at',
        'consent_version', 'consent_text',
    ];

    protected $hidden = ['document_number', 'phone', 'email', 'address'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'active_start' => 'datetime',
            'consent_accepted_at' => 'datetime',
            'price' => 'integer',
            'document_number' => 'encrypted',
            'phone' => 'encrypted',
            'address' => 'encrypted',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** ¿Tiene un pago que todavía puede terminar en cobro? */
    public function hasPaymentInProgress(): bool
    {
        return $this->payments()->whereIn('status', Payment::IN_PROGRESS)->exists();
    }

    /** ¿Sigue retenida a la espera del pago? */
    public function isHeld(): bool
    {
        return $this->status === self::PENDING
            && $this->hold_expires_at !== null
            && $this->hold_expires_at->isFuture();
    }

    /**
     * Cancela una retención pendiente (el paciente cambió de hora): libera el
     * horario y borra sus datos personales. No suelta una reserva con un pago en
     * curso: esa hora se libera sola si el pago no se completa.
     */
    public function cancelHold(): void
    {
        if ($this->status !== self::PENDING || $this->hasPaymentInProgress()) {
            return;
        }

        $this->forceFill(array_fill_keys(self::PATIENT_FIELDS, null) + [
            'status' => self::CANCELLED,
            'active_start' => null,
        ])->save();
    }

    /**
     * Reservas que ocupan el horario ahora: confirmadas, o pendientes cuya
     * retención de pago todavía no vence.
     */
    public function scopeOccupying(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $now ??= now();

        return $query->where(function (Builder $q) use ($now) {
            $q->where('status', self::CONFIRMED)
                ->orWhere(function (Builder $q) use ($now) {
                    $q->where('status', self::PENDING)->where('hold_expires_at', '>', $now);
                });
        });
    }

    /**
     * Libera las retenciones vencidas: pasan a "expired" y suelta active_start
     * para que la hora pueda reservarse de nuevo. Se llama antes de crear una
     * reserva y desde el cron.
     *
     * @return int cantidad de retenciones liberadas
     */
    public static function releaseExpiredHolds(?CarbonInterface $now = null): int
    {
        return static::query()
            ->where('status', self::PENDING)
            ->where('hold_expires_at', '<=', $now ?? now())
            ->update(['status' => self::EXPIRED, 'active_start' => null]);
    }

    /**
     * Borra los datos personales de reservas que no se completaron, pasado el
     * plazo de conservación (deja la fila sin datos, para estadísticas).
     *
     * @return int cantidad de reservas depuradas
     */
    public static function purgeAbandonedData(): int
    {
        $limit = now()->subDays((int) config('reservas.abandoned_data_days'));

        return static::query()
            ->whereIn('status', [self::EXPIRED, self::CANCELLED])
            ->whereNotNull('patient_name')
            ->where('updated_at', '<=', $limit)
            ->update(array_fill_keys(self::PATIENT_FIELDS, null));
    }
}
