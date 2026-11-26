<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un intento de pago de una reserva. */
class Payment extends Model
{
    /** Enviado a Webpay, sin respuesta todavía. */
    public const CREATED = 'created';

    /** Confirmación (commit) en curso. */
    public const COMMITTING = 'committing';

    /** Se intentó confirmar y no hubo respuesta: lo resuelve la conciliación. */
    public const UNKNOWN = 'unknown';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /** El paciente anuló en Webpay. */
    public const ABORTED = 'aborted';

    /** Se agotó el tiempo en el formulario de Webpay. */
    public const TIMEOUT = 'timeout';

    /** Falló la creación o el formulario de Webpay. */
    public const ERROR = 'error';

    /** Nunca se pagó ni se volvió desde Webpay. */
    public const ABANDONED = 'abandoned';

    /** La hora ya no estaba libre: no se confirmó el cobro. */
    public const SLOT_LOST = 'slot_lost';

    /** Requiere revisión manual. */
    public const REVIEW = 'review';

    public const REFUNDED = 'refunded';

    /** Estados en que todavía puede terminar en cobro. */
    public const IN_PROGRESS = [self::CREATED, self::COMMITTING, self::UNKNOWN];

    protected $fillable = [
        'booking_id', 'environment', 'buy_order', 'token', 'amount', 'status',
        'response_code', 'authorization_code', 'payment_type_code', 'card_last4',
        'installments', 'transaction_date', 'committed_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'response_code' => 'integer',
            'installments' => 'integer',
            'transaction_date' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
