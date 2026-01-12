<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Shelf;
use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    public function save(Stock $stock, bool $flush = false): void
    {
        $this->getEntityManager()->persist($stock);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Stock $stock, bool $flush = false): void
    {
        $this->getEntityManager()->remove($stock);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByProductAndShelf(Product $product, Shelf $shelf): ?Stock
    {
        return $this->findOneBy(['product' => $product, 'shelf' => $shelf]);
    }

    /**
     * @return Stock[]
     */
    public function findByProduct(Product $product): array
    {
        return $this->findBy(['product' => $product]);
    }

    /**
     * @return Stock[]
     */
    public function findByShelf(Shelf $shelf): array
    {
        return $this->findBy(['shelf' => $shelf]);
    }

    public function getTotalStockForProduct(Product $product): int
    {
        $result = $this->createQueryBuilder('s')
            ->select('SUM(s.quantity) as total')
            ->where('s.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)($result ?? 0);
    }

    public function getAvailableStockForProduct(Product $product): int
    {
        $result = $this->createQueryBuilder('s')
            ->select('SUM(s.quantity - s.reservedQuantity) as total')
            ->where('s.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();

        return (int)($result ?? 0);
    }

    /**
     * @return Stock[]
     */
    public function findAvailableStockForProduct(Product $product, int $requiredQuantity): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.shelf', 'shelf')
            ->where('s.product = :product')
            ->andWhere('(s.quantity - s.reservedQuantity) > 0')
            ->setParameter('product', $product)
            ->orderBy('shelf.aisle', 'ASC')
            ->addOrderBy('shelf.rack', 'ASC')
            ->addOrderBy('shelf.level', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getOrCreate(Product $product, Shelf $shelf): Stock
    {
        $stock = $this->findByProductAndShelf($product, $shelf);

        if (!$stock) {
            $stock = new Stock();
            $stock->setProduct($product);
            $stock->setShelf($shelf);
        }

        return $stock;
    }

    /**
     * @return array<array{product: Product, totalQuantity: int, availableQuantity: int}>
     */
    public function getInventorySummary(): array
    {
        return $this->createQueryBuilder('s')
            ->select('p.id, p.sku, p.name, SUM(s.quantity) as totalQuantity, SUM(s.quantity - s.reservedQuantity) as availableQuantity')
            ->join('s.product', 'p')
            ->groupBy('p.id')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

