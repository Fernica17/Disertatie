<?php

namespace App\Repository;

use App\Entity\Elements;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Elements>
 */
class ElementsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Elements::class);
    }

    public function choicesByListSlugQueryBuilder(string $slug, ?int $currentId = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->join('e.list', 'l')
            ->where('l.slug = :slug')
            ->setParameter('slug', $slug)
            ->orderBy('e.name', 'ASC');

        if ($currentId !== null) {
            $qb->andWhere('e.isActive = :active OR e.id = :currentId')
                ->setParameter('active', true)
                ->setParameter('currentId', $currentId);
        } else {
            $qb->andWhere('e.isActive = :active')
                ->setParameter('active', true);
        }

        return $qb;
    }

    /**
     * @return Elements[]
     */
    public function findByListSlug(string $slug): array
    {
        return $this->choicesByListSlugQueryBuilder($slug)
            ->getQuery()
            ->getResult();
    }
}
