<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\StockMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockMovement>
 */
class StockMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMovement::class);
    }

    public function save(StockMovement $movement, bool $flush = false): void
    {
        $this->getEntityManager()->persist($movement);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return StockMovement[]
     */
    public function findByProduct(Product $product): array
    {
        return $this->findBy(['product' => $product], ['createdAt' => 'DESC']);
    }

    /**
     * @return StockMovement[]
     */
    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type], ['createdAt' => 'DESC']);
    }

    /**
     * @return StockMovement[]
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('sm')
            ->where('sm.createdAt >= :from')
            ->andWhere('sm.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('sm.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return StockMovement[]
     */
    public function findTodayMovements(): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        return $this->findByDateRange($today, $tomorrow);
    }

    /**
     * Get daily summary of movements
     * @return array<string, array{receipts: int, picks: int, returns: int}>
     */
    public function getDailySummary(\DateTimeInterface $date): array
    {
        $start = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $end = $start->modify('+1 day');

        $results = $this->createQueryBuilder('sm')
            ->select('sm.type, SUM(sm.quantity) as total')
            ->where('sm.createdAt >= :start')
            ->andWhere('sm.createdAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('sm.type')
            ->getQuery()
            ->getResult();

        $summary = [
            'receipts' => 0,
            'picks' => 0,
            'returns' => 0,
        ];

        foreach ($results as $row) {
            match ($row['type']) {
                StockMovement::TYPE_RECEIPT => $summary['receipts'] = (int)$row['total'],
                StockMovement::TYPE_PICK => $summary['picks'] = (int)$row['total'],
                StockMovement::TYPE_RETURN => $summary['returns'] = (int)$row['total'],
                default => null,
            };
        }

        return $summary;
    }
}

