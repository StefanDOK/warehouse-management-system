<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GoodsReceipt;
use App\Entity\GoodsReceiptItem;
use App\Entity\Product;
use App\Entity\Shelf;
use App\Entity\Stock;
use App\Entity\StockMovement;
use App\Repository\ShelfRepository;
use App\Repository\StockRepository;
use App\Repository\StockMovementRepository;
use Doctrine\ORM\EntityManagerInterface;

class StockAllocationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShelfRepository $shelfRepository,
        private readonly StockRepository $stockRepository,
        private readonly StockMovementRepository $stockMovementRepository,
    ) {
    }

    /**
     * Automatically allocate stock to the best available shelf
     */
    public function allocateToShelf(Product $product, int $quantity, ?string $reference = null): ?array
    {
        // Find existing stock locations for this product
        $existingStocks = $this->stockRepository->findByProduct($product);

        // Try to add to existing stock location first
        foreach ($existingStocks as $stock) {
            if ($stock->getShelf()->hasAvailableSpace($quantity)) {
                return $this->addToStock($stock, $quantity, $reference);
            }
        }

        // Find best new shelf
        $shelf = $this->shelfRepository->findBestShelfForAllocation($quantity);

        if (!$shelf) {
            return null; // No available space
        }

        return $this->createNewStock($product, $shelf, $quantity, $reference);
    }

    /**
     * Allocate received goods from goods receipt to shelves
     */
    public function allocateFromGoodsReceipt(GoodsReceipt $goodsReceipt): array
    {
        $results = [];

        foreach ($goodsReceipt->getItems() as $item) {
            if ($item->getReceivedQuantity() > 0) {
                $result = $this->allocateGoodsReceiptItem($item);
                $results[] = [
                    'product' => [
                        'id' => $item->getProduct()->getId(),
                        'sku' => $item->getProduct()->getSku(),
                        'name' => $item->getProduct()->getName(),
                    ],
                    'quantity' => $item->getReceivedQuantity(),
                    'allocation' => $result,
                ];
            }
        }

        return $results;
    }

    /**
     * Allocate a single goods receipt item
     */
    public function allocateGoodsReceiptItem(GoodsReceiptItem $item): array
    {
        $product = $item->getProduct();
        $quantity = $item->getReceivedQuantity();
        $reference = $item->getGoodsReceipt()?->getReceiptNumber();

        $allocation = $this->allocateToShelf($product, $quantity, $reference);

        if ($allocation && isset($allocation['shelf'])) {
            $item->setAllocatedShelf($allocation['shelf']);
            $this->entityManager->flush();
        }

        return $allocation ?? ['success' => false, 'message' => 'No available shelf space'];
    }

    /**
     * Allocate to a specific shelf
     */
    public function allocateToSpecificShelf(Product $product, Shelf $shelf, int $quantity, ?string $reference = null): array
    {
        if (!$shelf->hasAvailableSpace($quantity)) {
            return [
                'success' => false,
                'message' => 'Shelf does not have enough space',
                'availableCapacity' => $shelf->getAvailableCapacity(),
                'requiredQuantity' => $quantity,
            ];
        }

        $stock = $this->stockRepository->getOrCreate($product, $shelf);
        return $this->addToStock($stock, $quantity, $reference);
    }

    /**
     * Suggest best shelf for a product
     */
    public function suggestShelf(Product $product, int $quantity): ?array
    {
        // First check existing locations
        $existingStocks = $this->stockRepository->findByProduct($product);
        foreach ($existingStocks as $stock) {
            if ($stock->getShelf()->hasAvailableSpace($quantity)) {
                return [
                    'shelf' => $stock->getShelf(),
                    'reason' => 'existing_location',
                    'currentQuantity' => $stock->getQuantity(),
                    'availableSpace' => $stock->getShelf()->getAvailableCapacity(),
                ];
            }
        }

        // Find new shelf
        $shelf = $this->shelfRepository->findBestShelfForAllocation($quantity);

        if ($shelf) {
            return [
                'shelf' => $shelf,
                'reason' => 'best_available',
                'currentQuantity' => 0,
                'availableSpace' => $shelf->getAvailableCapacity(),
            ];
        }

        return null;
    }

    /**
     * Get allocation suggestions for multiple products
     * @param array<array{product: Product, quantity: int}> $items
     */
    public function suggestAllocations(array $items): array
    {
        $suggestions = [];

        foreach ($items as $item) {
            $suggestion = $this->suggestShelf($item['product'], $item['quantity']);
            $suggestions[] = [
                'product' => [
                    'id' => $item['product']->getId(),
                    'sku' => $item['product']->getSku(),
                    'name' => $item['product']->getName(),
                ],
                'quantity' => $item['quantity'],
                'suggestion' => $suggestion ? [
                    'shelfCode' => $suggestion['shelf']->getCode(),
                    'shelfLocation' => $suggestion['shelf']->getFullLocation(),
                    'reason' => $suggestion['reason'],
                    'availableSpace' => $suggestion['availableSpace'],
                ] : null,
            ];
        }

        return $suggestions;
    }

    private function addToStock(Stock $stock, int $quantity, ?string $reference): array
    {
        $previousQuantity = $stock->getQuantity();
        $stock->addQuantity($quantity);

        // Create movement record
        $movement = StockMovement::createReceipt(
            $stock->getProduct(),
            $stock->getShelf(),
            $quantity,
            $reference ?? 'manual'
        );
        $movement->setPreviousQuantity($previousQuantity);
        $movement->setNewQuantity($stock->getQuantity());

        $this->entityManager->persist($stock);
        $this->entityManager->persist($movement);
        $this->entityManager->flush();

        return [
            'success' => true,
            'action' => 'added_to_existing',
            'shelf' => $stock->getShelf(),
            'shelfCode' => $stock->getShelf()->getCode(),
            'shelfLocation' => $stock->getShelf()->getFullLocation(),
            'previousQuantity' => $previousQuantity,
            'addedQuantity' => $quantity,
            'newQuantity' => $stock->getQuantity(),
        ];
    }

    private function createNewStock(Product $product, Shelf $shelf, int $quantity, ?string $reference): array
    {
        $stock = new Stock();
        $stock->setProduct($product);
        $stock->setShelf($shelf);
        $stock->setQuantity($quantity);

        // Create movement record
        $movement = StockMovement::createReceipt($product, $shelf, $quantity, $reference ?? 'manual');
        $movement->setPreviousQuantity(0);
        $movement->setNewQuantity($quantity);

        $this->entityManager->persist($stock);
        $this->entityManager->persist($movement);
        $this->entityManager->flush();

        return [
            'success' => true,
            'action' => 'created_new',
            'shelf' => $shelf,
            'shelfCode' => $shelf->getCode(),
            'shelfLocation' => $shelf->getFullLocation(),
            'previousQuantity' => 0,
            'addedQuantity' => $quantity,
            'newQuantity' => $quantity,
        ];
    }
}

