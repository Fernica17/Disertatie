<?php

namespace App\Traits;

trait CountByStatusTrait
{
    /**
     * Counts entities grouped by their status field.
     *
     * @return array<string, int> Map of status value => count
     */
    protected function countGroupedByStatus(string $alias = 'e'): array
    {
        $results = $this->createQueryBuilder($alias)
            ->select("{$alias}.status, COUNT({$alias}.id) as cnt")
            ->groupBy("{$alias}.status")
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['status']->value] = (int) $row['cnt'];
        }

        return $counts;
    }
}
