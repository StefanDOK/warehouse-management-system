<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GoodsReceipt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoodsReceipt>
 */
class GoodsReceiptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoodsReceipt::class);
    }

    public function save(GoodsReceipt $goodsReceipt, bool $flush = false): void
    {
        $this->getEntityManager()->persist($goodsReceipt);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GoodsReceipt $goodsReceipt, bool $flush = false): void
    {
        $this->getEntityManager()->remove($goodsReceipt);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByReceiptNumber(string $receiptNumber): ?GoodsReceipt
    {
        return $this->findOneBy(['receiptNumber' => $receiptNumber]);
    }

    /**
     * @return GoodsReceipt[]
     */
    public function findPending(): array
    {
        return $this->findBy(['status' => GoodsReceipt::STATUS_PENDING], ['createdAt' => 'DESC']);
    }

    /**
     * @return GoodsReceipt[]
     */
    public function findInProgress(): array
    {
        return $this->findBy(['status' => GoodsReceipt::STATUS_IN_PROGRESS], ['createdAt' => 'DESC']);
    }

    /**
     * @return GoodsReceipt[]
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('gr')
            ->orderBy('gr.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return GoodsReceipt[]
     */
    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('gr')
            ->where('gr.createdAt >= :from')
            ->andWhere('gr.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('gr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

