<?php

namespace App\Support;

/** Validación y formato de RUT chileno (módulo 11). */
final class Rut
{
    /**
     * Devuelve el RUT como "12345678-5" o null si no es válido.
     * Acepta puntos, guion y espacios.
     */
    public static function normalize(string $value): ?string
    {
        $clean = strtoupper((string) preg_replace('/[.\s-]/', '', $value));

        if (! preg_match('/^(\d{6,8})([\dK])$/', $clean, $matches)) {
            return null;
        }

        [, $body, $digit] = $matches;
        $body = ltrim($body, '0');

        if ($body === '' || self::checkDigit($body) !== $digit) {
            return null;
        }

        return $body.'-'.$digit;
    }

    public static function checkDigit(string $body): string
    {
        $sum = 0;
        $factor = 2;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += (int) $body[$i] * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $result = 11 - ($sum % 11);

        return match ($result) {
            11 => '0',
            10 => 'K',
            default => (string) $result,
        };
    }
}
