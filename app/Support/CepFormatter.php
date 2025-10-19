<?php

namespace App\Support;

final class CepFormatter
{
    public static function format(?string $cep): ?string
    {
        if ($cep === null) {
            return null;
        }

        $trimmed = trim($cep);

        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $trimmed);

        if ($digits === null || strlen($digits) !== 8) {
            return $trimmed;
        }

        return substr($digits, 0, 5) . '-' . substr($digits, 5);
    }

    public static function strip(?string $cep): ?string
    {
        if ($cep === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $cep);

        if ($digits === null) {
            return null;
        }

        $trimmed = substr($digits, 0, 8);

        return strlen($trimmed) === 8 ? $trimmed : null;
    }

    public static function uppercaseState(?string $state): ?string
    {
        if ($state === null) {
            return null;
        }

        $trimmed = trim($state);

        if ($trimmed === '') {
            return null;
        }

        return mb_strtoupper($trimmed);
    }
}
