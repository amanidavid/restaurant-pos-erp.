<?php

namespace App\Support;

final class TanzaniaPhoneNumber
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        } elseif (preg_match('/^[67]\d{8}$/', $digits) === 1) {
            $digits = '255'.$digits;
        }

        return '+'.$digits;
    }
}
