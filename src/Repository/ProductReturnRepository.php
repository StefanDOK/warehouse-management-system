<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductReturn;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductReturn>
 */
class ProductReturnRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductReturn::class);
    }

    public function save(ProductReturn $return, bool $flush = false): void
    {
        $this->getEntityManager()->persist($return);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductReturn $return, bool $flush = false): void
    {
        $this->getEntityManager()->remove($return);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByReturnNumber(string $returnNumber): ?ProductReturn
    {
        return $this->findOneBy(['returnNumber' => $returnNumber]);
    }

    /**
     * @return ProductReturn[]
     */
    public function findPending(): array
    {
        return $this->findBy(['status' => ProductReturn::STATUS_PENDING], ['createdAt' => 'DESC']);
    }

    /**
     * @return ProductReturn[]
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

