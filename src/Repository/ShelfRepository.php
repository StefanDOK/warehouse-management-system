<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shelf;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Shelf>
 */
class ShelfRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shelf::class);
    }

    public function save(Shelf $shelf, bool $flush = false): void
    {
        $this->getEntityManager()->persist($shelf);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Shelf $shelf, bool $flush = false): void
    {
        $this->getEntityManager()->remove($shelf);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCode(string $code): ?Shelf
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * @return Shelf[]
     */
    public function findActiveShelves(): array
    {
        return $this->findBy(['isActive' => true], ['code' => 'ASC']);
    }

    /**
     * @return Shelf[]
     */
    public function findAvailableShelves(int $requiredCapacity = 1): array
    {
        $shelves = $this->findActiveShelves();

        return array_filter($shelves, fn(Shelf $s) => $s->hasAvailableSpace($requiredCapacity));
    }

    /**
     * Find the best shelf for allocation (most available space)
     */
    public function findBestShelfForAllocation(int $quantity): ?Shelf
    {
        $shelves = $this->findAvailableShelves($quantity);

        if (empty($shelves)) {
            return null;
        }

        usort($shelves, fn(Shelf $a, Shelf $b) => $b->getAvailableCapacity() <=> $a->getAvailableCapacity());

        return $shelves[0] ?? null;
    }

    /**
     * @return Shelf[]
     */
    public function findByAisle(string $aisle): array
    {
        return $this->findBy(['aisle' => $aisle, 'isActive' => true], ['rack' => 'ASC', 'level' => 'ASC']);
    }
}

