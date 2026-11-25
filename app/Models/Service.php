<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    public const INDIVIDUAL = 'individual';

    public const COUPLE = 'pareja';

    protected $fillable = ['professional_id', 'kind', 'price', 'duration_minutes', 'active'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'duration_minutes' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function label(): string
    {
        return match ($this->kind) {
            self::COUPLE => 'Terapia de pareja',
            default => 'Terapia individual',
        };
    }

    /** Precio con formato chileno, por ejemplo $40.000. */
    public function formattedPrice(): string
    {
        return '$'.number_format($this->price, 0, ',', '.');
    }
}
