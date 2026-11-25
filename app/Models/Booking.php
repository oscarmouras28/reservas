<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    protected $fillable = [
        'professional_id', 'service_id', 'starts_at', 'ends_at', 'status',
        'hold_expires_at', 'active_start', 'price',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'active_start' => 'datetime',
            'price' => 'integer',
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
}
