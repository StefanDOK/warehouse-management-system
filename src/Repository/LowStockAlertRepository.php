<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LowStockAlert;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LowStockAlert>
 */
class LowStockAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LowStockAlert::class);
    }

    public function save(LowStockAlert $alert, bool $flush = false): void
    {
        $this->getEntityManager()->persist($alert);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return LowStockAlert[]
     */
    public function findActive(): array
    {
        return $this->findBy(['status' => LowStockAlert::STATUS_ACTIVE], ['createdAt' => 'DESC']);
    }

    /**
     * @return LowStockAlert[]
     */
    public function findActiveByProduct(Product $product): array
    {
        return $this->findBy([
            'product' => $product,
            'status' => LowStockAlert::STATUS_ACTIVE,
        ]);
    }

    /**
     * @return LowStockAlert[]
     */
    public function findBySeverity(string $severity): array
    {
        return $this->findBy([
            'severity' => $severity,
            'status' => LowStockAlert::STATUS_ACTIVE,
        ], ['createdAt' => 'DESC']);
    }

    /**
     * @return LowStockAlert[]
     */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int)$this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :status')
            ->setParameter('status', LowStockAlert::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasActiveAlert(Product $product): bool
    {
        return count($this->findActiveByProduct($product)) > 0;
    }
}

