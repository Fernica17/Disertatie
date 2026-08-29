<?php

namespace App\Twig;

use App\Helper\NumberHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class NumberExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_number', $this->formatNumber(...)),
            new TwigFilter('format_qty', $this->formatNumber(...)),
        ];
    }

    public function formatNumber(string|float|int|null $value, int $maxDecimals = 2, bool $trimZeros = true): string
    {
        return NumberHelper::formatNumber($value, $maxDecimals, $trimZeros);
    }
}
