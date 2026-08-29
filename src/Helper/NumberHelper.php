<?php

namespace App\Helper;

class NumberHelper
{
    /**
     * Format a number.
     */
    public static function formatNumber(string|float|int|null $value, int $maxDecimals = 2, bool $trimZeros = true): string
    {
        if ($value === null) {
            return '0';
        }

        $formatted = number_format((float) $value, $maxDecimals, ',', '.');

        if ($trimZeros && $maxDecimals > 0) {
            $formatted = rtrim($formatted, '0');
            $formatted = rtrim($formatted, ',');
        }

        return $formatted;
    }
}
