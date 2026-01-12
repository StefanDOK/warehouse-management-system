<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PickList;
use App\Entity\PickListItem;
use App\Entity\Stock;
use App\Entity\StockMovement;
use App\Repository\PickListItemRepository;
use App\Repository\StockRepository;
use App\Repository\StockMovementRepository;
use Doctrine\ORM\EntityManagerInterface;

class PickingScanService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PickListItemRepository $pickListItemRepository,
        private readonly StockRepository $stockRepository,
        private readonly StockMovementRepository $stockMovementRepository,
    ) {
    }

    /**
     * Scan barcode during picking process
     */
    public function scanItem(PickListItem $item, string $barcode, int $quantity = 1): array
    {
        $product = $item->getProduct();
        $expectedBarcode = $product->getBarcode();

        // Validate barcode
        if ($expectedBarcode !== $barcode) {
            return [
                'success' => false,
                'message' => 'Barcode mismatch - wrong product',
                'expected' => $expectedBarcode,
                'scanned' => $barcode,
            ];
        }

        // Check if already fully picked
        if ($item->isPicked()) {
            return [
                'success' => false,
                'message' => 'Item already fully picked',
                'pickedQuantity' => $item->getPickedQuantity(),
                'requiredQuantity' => $item->getQuantity(),
            ];
        }

        // Validate quantity
        $remainingQuantity = $item->getRemainingQuantity();
        if ($quantity > $remainingQuantity) {
            return [
                'success' => false,
                'message' => 'Quantity exceeds remaining quantity',
                'scannedQuantity' => $quantity,
                'remainingQuantity' => $remainingQuantity,
            ];
        }

        // Update picked quantity
        $item->pick($item->getPickedQuantity() + $quantity, $barcode);

        // Update stock
        $stock = $this->stockRepository->findByProductAndShelf($product, $item->getShelf());
        if ($stock) {
            $previousQuantity = $stock->getQuantity();
            $stock->removeQuantity($quantity);
            $stock->releaseReservation($quantity);

            // Create stock movement
            $movement = StockMovement::createPick(
                $product,
                $item->getShelf(),
                $quantity,
                $item->getPickList()?->getPickListNumber() ?? 'unknown'
            );
            $movement->setPreviousQuantity($previousQuantity);
            $movement->setNewQuantity($stock->getQuantity());

            $this->entityManager->persist($movement);
        }

        $this->entityManager->flush();

        return [
            'success' => true,
            'message' => 'Item picked successfully',
            'product' => [
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'barcode' => $product->getBarcode(),
            ],
            'quantity' => [
                'scanned' => $quantity,
                'picked' => $item->getPickedQuantity(),
                'required' => $item->getQuantity(),
                'remaining' => $item->getRemainingQuantity(),
            ],
            'isPicked' => $item->isPicked(),
            'location' => $item->getShelfLocation(),
        ];
    }

    /**
     * Scan and pick all remaining quantity for an item
     */
    public function scanAndPickAll(PickListItem $item, string $barcode): array
    {
        $remainingQuantity = $item->getRemainingQuantity();
        return $this->scanItem($item, $barcode, $remainingQuantity);
    }

    /**
     * Find pick list item by barcode in a pick list
     */
    public function findItemByBarcodeInPickList(PickList $pickList, string $barcode): ?PickListItem
    {
        foreach ($pickList->getItems() as $item) {
            if ($item->getProduct()->getBarcode() === $barcode && !$item->isPicked()) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Quick scan - find item by barcode and pick
     */
    public function quickScanAndPick(PickList $pickList, string $barcode, int $quantity = 1): array
    {
        $item = $this->findItemByBarcodeInPickList($pickList, $barcode);

        if (!$item) {
            return [
                'success' => false,
                'message' => 'No unpicked item found for this barcode in pick list',
                'barcode' => $barcode,
            ];
        }

        return $this->scanItem($item, $barcode, $quantity);
    }

    /**
     * Validate location before picking
     */
    public function validateLocation(PickListItem $item, string $locationCode): bool
    {
        return $item->getShelf()->getCode() === $locationCode
            || $item->getShelfLocation() === $locationCode;
    }

    /**
     * Scan with location validation
     */
    public function scanWithLocationValidation(
        PickListItem $item,
        string $barcode,
        string $locationCode,
        int $quantity = 1
    ): array {
        // First validate location
        if (!$this->validateLocation($item, $locationCode)) {
            return [
                'success' => false,
                'message' => 'Location mismatch',
                'expectedLocation' => $item->getShelfLocation(),
                'scannedLocation' => $locationCode,
            ];
        }

        // Then validate and pick
        return $this->scanItem($item, $barcode, $quantity);
    }

    /**
     * Get picking progress for a pick list
     */
    public function getPickingProgress(PickList $pickList): array
    {
        $items = $pickList->getItems();
        $totalItems = $items->count();
        $pickedItems = 0;
        $totalQuantity = 0;
        $pickedQuantity = 0;

        foreach ($items as $item) {
            $totalQuantity += $item->getQuantity();
            $pickedQuantity += $item->getPickedQuantity();
            if ($item->isPicked()) {
                $pickedItems++;
            }
        }

        return [
            'pickListNumber' => $pickList->getPickListNumber(),
            'status' => $pickList->getStatus(),
            'items' => [
                'total' => $totalItems,
                'picked' => $pickedItems,
                'remaining' => $totalItems - $pickedItems,
            ],
            'quantity' => [
                'total' => $totalQuantity,
                'picked' => $pickedQuantity,
                'remaining' => $totalQuantity - $pickedQuantity,
            ],
            'progressPercent' => $pickList->getProgress(),
            'isComplete' => $pickList->isComplete(),
        ];
    }
}

