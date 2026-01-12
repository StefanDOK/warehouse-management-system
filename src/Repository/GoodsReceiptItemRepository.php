<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GoodsReceiptItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoodsReceiptItem>
 */
class GoodsReceiptItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoodsReceiptItem::class);
    }

    public function save(GoodsReceiptItem $item, bool $flush = false): void
    {
        $this->getEntityManager()->persist($item);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GoodsReceiptItem $item, bool $flush = false): void
    {
        $this->getEntityManager()->remove($item);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

