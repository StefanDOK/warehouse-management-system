<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\Shelf;
use App\Entity\Stock;
use App\Entity\StockMovement;
use App\Repository\ProductRepository;
use App\Repository\ShelfRepository;
use App\Repository\StockRepository;
use App\Repository\StockMovementRepository;
use Doctrine\ORM\EntityManagerInterface;

class StockService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockRepository $stockRepository,
        private readonly ProductRepository $productRepository,
        private readonly ShelfRepository $shelfRepository,
        private readonly StockMovementRepository $stockMovementRepository,
    ) {
    }

    /**
     * Get real-time stock for a product
     */
    public function getProductStock(Product $product): array
    {
        $stocks = $this->stockRepository->findByProduct($product);
        $totalQuantity = 0;
        $totalReserved = 0;
        $locations = [];

        foreach ($stocks as $stock) {
            $totalQuantity += $stock->getQuantity();
            $totalReserved += $stock->getReservedQuantity();
            $locations[] = [
                'shelfCode' => $stock->getShelf()->getCode(),
                'location' => $stock->getShelf()->getFullLocation(),
                'quantity' => $stock->getQuantity(),
                'reserved' => $stock->getReservedQuantity(),
                'available' => $stock->getAvailableQuantity(),
                'lastUpdate' => $stock->getUpdatedAt()->format('c'),
            ];
        }

        return [
            'product' => [
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'barcode' => $product->getBarcode(),
                'minStockLevel' => $product->getMinStockLevel(),
            ],
            'stock' => [
                'total' => $totalQuantity,
                'reserved' => $totalReserved,
                'available' => $totalQuantity - $totalReserved,
                'isLowStock' => $product->isLowStock(),
            ],
            'locations' => $locations,
        ];
    }

    /**
     * Update stock quantity (add or remove)
     */
    public function updateStock(
        Product $product,
        Shelf $shelf,
        int $quantityChange,
        string $type,
        ?string $reference = null,
        ?string $notes = null
    ): array {
        $stock = $this->stockRepository->getOrCreate($product, $shelf);
        $previousQuantity = $stock->getQuantity();

        if ($quantityChange > 0) {
            $stock->addQuantity($quantityChange);
        } else {
            $stock->removeQuantity(abs($quantityChange));
        }

        // Create movement record
        $movement = new StockMovement();
        $movement->setProduct($product);
        $movement->setType($type);
        $movement->setQuantity(abs($quantityChange));
        $movement->setPreviousQuantity($previousQuantity);
        $movement->setNewQuantity($stock->getQuantity());
        $movement->setReference($reference);
        $movement->setNotes($notes);

        if ($quantityChange > 0) {
            $movement->setToShelf($shelf);
        } else {
            $movement->setFromShelf($shelf);
        }

        $this->entityManager->persist($stock);
        $this->entityManager->persist($movement);
        $this->entityManager->flush();

        return [
            'success' => true,
            'previousQuantity' => $previousQuantity,
            'change' => $quantityChange,
            'newQuantity' => $stock->getQuantity(),
            'location' => $shelf->getFullLocation(),
        ];
    }

    /**
     * Adjust stock (for inventory corrections)
     */
    public function adjustStock(
        Product $product,
        Shelf $shelf,
        int $newQuantity,
        ?string $reason = null
    ): array {
        $stock = $this->stockRepository->getOrCreate($product, $shelf);
        $previousQuantity = $stock->getQuantity();
        $difference = $newQuantity - $previousQuantity;

        $stock->setQuantity($newQuantity);

        // Create adjustment movement
        $movement = new StockMovement();
        $movement->setProduct($product);
        $movement->setType(StockMovement::TYPE_ADJUSTMENT);
        $movement->setQuantity(abs($difference));
        $movement->setPreviousQuantity($previousQuantity);
        $movement->setNewQuantity($newQuantity);
        $movement->setNotes($reason);

        if ($difference > 0) {
            $movement->setToShelf($shelf);
        } else {
            $movement->setFromShelf($shelf);
        }

        $this->entityManager->persist($stock);
        $this->entityManager->persist($movement);
        $this->entityManager->flush();

        return [
            'success' => true,
            'previousQuantity' => $previousQuantity,
            'newQuantity' => $newQuantity,
            'difference' => $difference,
            'location' => $shelf->getFullLocation(),
        ];
    }

    /**
     * Transfer stock between shelves
     */
    public function transferStock(
        Product $product,
        Shelf $fromShelf,
        Shelf $toShelf,
        int $quantity,
        ?string $reference = null
    ): array {
        $fromStock = $this->stockRepository->findByProductAndShelf($product, $fromShelf);

        if (!$fromStock || $fromStock->getAvailableQuantity() < $quantity) {
            return [
                'success' => false,
                'message' => 'Insufficient stock at source location',
                'available' => $fromStock?->getAvailableQuantity() ?? 0,
                'requested' => $quantity,
            ];
        }

        if (!$toShelf->hasAvailableSpace($quantity)) {
            return [
                'success' => false,
                'message' => 'Insufficient space at destination',
                'availableSpace' => $toShelf->getAvailableCapacity(),
                'requested' => $quantity,
            ];
        }

        $toStock = $this->stockRepository->getOrCreate($product, $toShelf);

        // Update quantities
        $fromPrevious = $fromStock->getQuantity();
        $toPrevious = $toStock->getQuantity();

        $fromStock->removeQuantity($quantity);
        $toStock->addQuantity($quantity);

        // Create transfer movement
        $movement = new StockMovement();
        $movement->setProduct($product);
        $movement->setType(StockMovement::TYPE_TRANSFER);
        $movement->setFromShelf($fromShelf);
        $movement->setToShelf($toShelf);
        $movement->setQuantity($quantity);
        $movement->setPreviousQuantity($fromPrevious);
        $movement->setNewQuantity($fromStock->getQuantity());
        $movement->setReference($reference);

        $this->entityManager->persist($fromStock);
        $this->entityManager->persist($toStock);
        $this->entityManager->persist($movement);
        $this->entityManager->flush();

        return [
            'success' => true,
            'from' => [
                'location' => $fromShelf->getFullLocation(),
                'previousQuantity' => $fromPrevious,
                'newQuantity' => $fromStock->getQuantity(),
            ],
            'to' => [
                'location' => $toShelf->getFullLocation(),
                'previousQuantity' => $toPrevious,
                'newQuantity' => $toStock->getQuantity(),
            ],
            'transferred' => $quantity,
        ];
    }

    /**
     * Get inventory summary
     */
    public function getInventorySummary(): array
    {
        return $this->stockRepository->getInventorySummary();
    }

    /**
     * Get stock movements for a product
     */
    public function getProductMovements(Product $product, int $limit = 50): array
    {
        $movements = $this->stockMovementRepository->findByProduct($product);

        return array_slice(array_map(fn(StockMovement $m) => [
            'id' => $m->getId(),
            'type' => $m->getType(),
            'quantity' => $m->getQuantity(),
            'previousQuantity' => $m->getPreviousQuantity(),
            'newQuantity' => $m->getNewQuantity(),
            'fromLocation' => $m->getFromShelf()?->getFullLocation(),
            'toLocation' => $m->getToShelf()?->getFullLocation(),
            'reference' => $m->getReference(),
            'notes' => $m->getNotes(),
            'createdAt' => $m->getCreatedAt()->format('c'),
        ], $movements), 0, $limit);
    }

    /**
     * Get recent stock movements
     */
    public function getRecentMovements(int $limit = 50): array
    {
        $movements = $this->stockMovementRepository->findTodayMovements();

        return array_slice(array_map(fn(StockMovement $m) => [
            'id' => $m->getId(),
            'product' => [
                'id' => $m->getProduct()->getId(),
                'sku' => $m->getProduct()->getSku(),
                'name' => $m->getProduct()->getName(),
            ],
            'type' => $m->getType(),
            'quantity' => $m->getQuantity(),
            'previousQuantity' => $m->getPreviousQuantity(),
            'newQuantity' => $m->getNewQuantity(),
            'fromLocation' => $m->getFromShelf()?->getFullLocation(),
            'toLocation' => $m->getToShelf()?->getFullLocation(),
            'reference' => $m->getReference(),
            'createdAt' => $m->getCreatedAt()->format('c'),
        ], $movements), 0, $limit);
    }
}

