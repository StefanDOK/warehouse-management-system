<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GoodsReceiptItem;
use App\Entity\Product;
use App\Repository\GoodsReceiptItemRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class BarcodeScannerService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
        private readonly GoodsReceiptItemRepository $goodsReceiptItemRepository,
    ) {
    }

    /**
     * Validate a barcode and return the associated product
     */
    public function validateBarcode(string $barcode): ?Product
    {
        return $this->productRepository->findByBarcode($barcode);
    }

    /**
     * Scan barcode at goods receipt and update received quantity
     */
    public function scanAtGoodsReceipt(
        GoodsReceiptItem $item,
        string $barcode,
        int $quantity = 1
    ): array {
        $product = $item->getProduct();

        // Validate barcode matches the expected product
        if ($product->getBarcode() !== $barcode) {
            return [
                'success' => false,
                'message' => 'Barcode does not match expected product',
                'expected' => $product->getBarcode(),
                'scanned' => $barcode,
            ];
        }

        // Update received quantity
        $currentReceived = $item->getReceivedQuantity();
        $newReceived = $currentReceived + $quantity;
        $expected = $item->getExpectedQuantity();

        $item->setReceivedQuantity($newReceived);
        $item->setScannedBarcode($barcode);

        $this->entityManager->flush();

        $status = 'partial';
        if ($newReceived >= $expected) {
            $status = 'complete';
        }
        
        if ($newReceived > $expected) {
            $status = 'over_received';
        }

        return [
            'success' => true,
            'message' => 'Barcode scanned successfully',
            'product' => [
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'barcode' => $product->getBarcode(),
            ],
            'quantity' => [
                'scanned' => $quantity,
                'received' => $newReceived,
                'expected' => $expected,
                'remaining' => max(0, $expected - $newReceived),
            ],
            'status' => $status,
            'isFullyReceived' => $item->isFullyReceived(),
        ];
    }

    /**
     * Quick scan - find product by barcode and return info
     */
    public function quickScan(string $barcode): array
    {
        $product = $this->validateBarcode($barcode);

        if (!$product) {
            return [
                'success' => false,
                'message' => 'Product not found for barcode',
                'barcode' => $barcode,
            ];
        }

        return [
            'success' => true,
            'product' => [
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'barcode' => $product->getBarcode(),
                'price' => $product->getPrice(),
                'totalStock' => $product->getTotalStock(),
                'isLowStock' => $product->isLowStock(),
            ],
        ];
    }

    /**
     * Batch scan multiple barcodes
     * @param string[] $barcodes
     * @return array<string, array>
     */
    public function batchScan(array $barcodes): array
    {
        $results = [];

        foreach ($barcodes as $barcode) {
            $results[$barcode] = $this->quickScan($barcode);
        }

        return $results;
    }

    /**
     * Validate that scanned barcode matches expected product
     */
    public function validateScanForItem(GoodsReceiptItem $item, string $barcode): bool
    {
        return $item->getProduct()->getBarcode() === $barcode;
    }
}

