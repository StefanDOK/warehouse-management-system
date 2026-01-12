<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function save(Product $product, bool $flush = false): void
    {
        $this->getEntityManager()->persist($product);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $product, bool $flush = false): void
    {
        $this->getEntityManager()->remove($product);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByBarcode(string $barcode): ?Product
    {
        return $this->findOneBy(['barcode' => $barcode]);
    }

    public function findBySku(string $sku): ?Product
    {
        return $this->findOneBy(['sku' => $sku]);
    }

    /**
     * @return Product[]
     */
    public function findLowStockProducts(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p', 's')
            ->leftJoin('p.stocks', 's')
            ->getQuery();

        $products = $qb->getResult();

        return array_filter($products, fn(Product $p) => $p->isLowStock());
    }

    /**
     * @return Product[]
     */
    public function search(string $term): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.name LIKE :term')
            ->orWhere('p.sku LIKE :term')
            ->orWhere('p.barcode LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

