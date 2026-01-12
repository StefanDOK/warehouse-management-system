<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductReturn;
use App\Entity\ProductReturnItem;
use App\Entity\StockMovement;
use App\Repository\ProductReturnRepository;
use App\Repository\ShelfRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProductReturnService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductReturnRepository $productReturnRepository,
        private readonly StockRepository $stockRepository,
        private readonly ShelfRepository $shelfRepository,
    ) {
    }

    /**
     * Create a new product return
     */
    public function createReturn(
        string $reason,
        ?Order $originalOrder = null,
        ?string $notes = null
    ): ProductReturn {
        $return = new ProductReturn();
        $return->setReason($reason);
        $return->setOriginalOrder($originalOrder);
        $return->setNotes($notes);

        $this->entityManager->persist($return);
        $this->entityManager->flush();

        return $return;
    }

    /**
     * Add item to return
     */
    public function addItem(
        ProductReturn $return,
        Product $product,
        int $quantity,
        string $condition = ProductReturnItem::CONDITION_GOOD,
        ?string $notes = null
    ): ProductReturnItem {
        $item = new ProductReturnItem();
        $item->setProduct($product);
        $item->setQuantity($quantity);
        $item->setCondition($condition);
        $item->setNotes($notes);

        $return->addItem($item);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }

    /**
     * Start inspection process
     */
    public function startInspection(ProductReturn $return): ProductReturn
    {
        if ($return->getStatus() !== ProductReturn::STATUS_PENDING) {
            throw new \InvalidArgumentException('Return must be pending to start inspection');
        }

        $return->startInspection();
        $this->entityManager->flush();

        return $return;
    }

    /**
     * Complete return and restock items
     */
    public function completeAndRestock(ProductReturn $return): array
    {
        $results = [];

        foreach ($return->getItems() as $item) {
            if ($item->isRestockable() && !$item->isRestocked()) {
                $result = $this->restockItem($item, $return->getReturnNumber());
                $results[] = $result;
            }
        }

        $return->complete();
        $this->entityManager->flush();

        return $results;
    }

    /**
     * Restock a single item
     */
    public function restockItem(ProductReturnItem $item, ?string $reference = null): array
    {
        $product = $item->getProduct();
        $quantity = $item->getQuantity();

        // Find or suggest shelf for restocking
        $shelf = $item->getAllocatedShelf();

        if (!$shelf) {
            // Find existing stock location for this product
            $existingStocks = $this->stockRepository->findByProduct($product);
            foreach ($existingStocks as $stock) {
                if ($stock->getShelf()->hasAvailableSpace($quantity)) {
                    $shelf = $stock->getShelf();
                    break;
                }
            }
        }

        if (!$shelf) {
            // Find best available shelf
            $shelf = $this->shelfRepository->findBestShelfForAllocation($quantity);
        }

        if (!$shelf) {
            return [
                'success' => false,
                'message' => 'No available shelf for restocking',
                'product' => $product->getSku(),
                'quantity' => $quantity,
            ];
        }

        // Update stock
        $stock = $this->stockRepository->getOrCreate($product, $shelf);
        $previousQuantity = $stock->getQuantity();
        $stock->addQuantity($quantity);

        // Create return movement
        $movement = StockMovement::createReturn($product, $shelf, $quantity, $reference ?? 'return');
        $movement->setPreviousQuantity($previousQuantity);
        $movement->setNewQuantity($stock->getQuantity());

        $item->setAllocatedShelf($shelf);
        $item->setRestocked(true);

        $this->entityManager->persist($stock);
        $this->entityManager->persist($movement);
        $this->entityManager->flush();

        return [
            'success' => true,
            'product' => [
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
            ],
            'quantity' => $quantity,
            'shelf' => [
                'code' => $shelf->getCode(),
                'location' => $shelf->getFullLocation(),
            ],
            'previousQuantity' => $previousQuantity,
            'newQuantity' => $stock->getQuantity(),
        ];
    }

    /**
     * Reject return (items not restockable)
     */
    public function rejectReturn(ProductReturn $return, ?string $notes = null): ProductReturn
    {
        if ($notes) {
            $return->setNotes(($return->getNotes() ?? '') . "\nRejection: " . $notes);
        }

        $return->reject();
        $this->entityManager->flush();

        return $return;
    }

    /**
     * Get return by ID
     */
    public function getReturn(int $id): ?ProductReturn
    {
        return $this->productReturnRepository->find($id);
    }

    /**
     * Get return by number
     */
    public function getReturnByNumber(string $returnNumber): ?ProductReturn
    {
        return $this->productReturnRepository->findByReturnNumber($returnNumber);
    }

    /**
     * Get pending returns
     * @return ProductReturn[]
     */
    public function getPendingReturns(): array
    {
        return $this->productReturnRepository->findPending();
    }

    /**
     * Get recent returns
     * @return ProductReturn[]
     */
    public function getRecentReturns(int $limit = 20): array
    {
        return $this->productReturnRepository->findRecent($limit);
    }
}

