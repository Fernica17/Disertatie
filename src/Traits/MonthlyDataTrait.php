<?php

namespace App\Traits;

trait MonthlyDataTrait
{
    protected function fillMonths(array $results, int $months, string $valueKey): array
    {
        $indexed = [];
        foreach ($results as $row) {
            $indexed[$row['month']] = round((float) $row[$valueKey]);
        }

        $filled = [];
        for ($i = $months; $i >= 1; --$i) {
            $month = (new \DateTimeImmutable("first day of -{$i} months"))->format('Y-m');
            $filled[$month] = $indexed[$month] ?? 0;
        }

        return $filled;
    }
}
