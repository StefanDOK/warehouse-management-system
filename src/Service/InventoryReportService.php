<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\GoodsReceiptRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\StockMovementRepository;
use App\Repository\StockRepository;

class InventoryReportService
{
    public function __construct(
        private readonly StockRepository $stockRepository,
        private readonly ProductRepository $productRepository,
        private readonly StockMovementRepository $stockMovementRepository,
        private readonly GoodsReceiptRepository $goodsReceiptRepository,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    /**
     * Generate daily inventory report
     */
    public function generateDailyReport(?\DateTimeInterface $date = null): array
    {
        $date = $date ?? new \DateTimeImmutable('today');
        $dateString = $date->format('Y-m-d');

        return [
            'reportDate' => $dateString,
            'generatedAt' => (new \DateTimeImmutable())->format('c'),
            'summary' => $this->getInventorySummary(),
            'movements' => $this->getDailyMovementsSummary($date),
            'lowStockProducts' => $this->getLowStockProducts(),
            'topMovingProducts' => $this->getTopMovingProducts($date),
            'activitySummary' => $this->getActivitySummary($date),
        ];
    }

    /**
     * Get inventory summary
     */
    public function getInventorySummary(): array
    {
        $stockData = $this->stockRepository->getInventorySummary();

        $totalProducts = count($stockData);
        $totalQuantity = 0;
        $totalAvailable = 0;
        $totalReserved = 0;

        foreach ($stockData as $item) {
            $totalQuantity += (int)$item['totalQuantity'];
            $totalAvailable += (int)$item['availableQuantity'];
        }

        $totalReserved = $totalQuantity - $totalAvailable;

        return [
            'totalProducts' => $totalProducts,
            'totalQuantity' => $totalQuantity,
            'availableQuantity' => $totalAvailable,
            'reservedQuantity' => $totalReserved,
            'utilizationPercent' => $totalQuantity > 0 
                ? round(($totalQuantity - $totalAvailable) / $totalQuantity * 100, 2) 
                : 0,
        ];
    }

    /**
     * Get daily movements summary
     */
    public function getDailyMovementsSummary(\DateTimeInterface $date): array
    {
        return $this->stockMovementRepository->getDailySummary($date);
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(): array
    {
        $products = $this->productRepository->findLowStockProducts();

        return array_map(fn($p) => [
            'id' => $p->getId(),
            'sku' => $p->getSku(),
            'name' => $p->getName(),
            'currentStock' => $p->getTotalStock(),
            'minStockLevel' => $p->getMinStockLevel(),
            'deficit' => $p->getMinStockLevel() - $p->getTotalStock(),
        ], $products);
    }

    /**
     * Get top moving products (most picked today)
     */
    public function getTopMovingProducts(\DateTimeInterface $date, int $limit = 10): array
    {
        $start = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $end = $start->modify('+1 day');

        $movements = $this->stockMovementRepository->findByDateRange($start, $end);

        $productPicks = [];
        foreach ($movements as $movement) {
            if ($movement->getType() === 'pick') {
                $productId = $movement->getProduct()->getId();
                if (!isset($productPicks[$productId])) {
                    $productPicks[$productId] = [
                        'product' => $movement->getProduct(),
                        'totalPicked' => 0,
                    ];
                }
                $productPicks[$productId]['totalPicked'] += $movement->getQuantity();
            }
        }

        // Sort by total picked
        usort($productPicks, fn($a, $b) => $b['totalPicked'] <=> $a['totalPicked']);

        return array_slice(array_map(fn($item) => [
            'id' => $item['product']->getId(),
            'sku' => $item['product']->getSku(),
            'name' => $item['product']->getName(),
            'totalPicked' => $item['totalPicked'],
        ], $productPicks), 0, $limit);
    }

    /**
     * Get activity summary for the day
     */
    public function getActivitySummary(\DateTimeInterface $date): array
    {
        $start = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $end = $start->modify('+1 day');

        $goodsReceipts = $this->goodsReceiptRepository->findByDateRange($start, $end);
        $orders = $this->orderRepository->findRecent(100);

        $ordersToday = array_filter($orders, function($order) use ($start, $end) {
            return $order->getCreatedAt() >= $start && $order->getCreatedAt() < $end;
        });

        $shippedToday = array_filter($orders, function($order) use ($start, $end) {
            return $order->getShippedAt() !== null 
                && $order->getShippedAt() >= $start 
                && $order->getShippedAt() < $end;
        });

        return [
            'goodsReceiptsCreated' => count($goodsReceipts),
            'ordersReceived' => count($ordersToday),
            'ordersShipped' => count($shippedToday),
        ];
    }

    /**
     * Generate inventory valuation report
     */
    public function generateValuationReport(): array
    {
        $products = $this->productRepository->findAll();
        $totalValue = 0;
        $items = [];

        foreach ($products as $product) {
            $quantity = $product->getTotalStock();
            $value = (float)$product->getPrice() * $quantity;
            $totalValue += $value;

            if ($quantity > 0) {
                $items[] = [
                    'id' => $product->getId(),
                    'sku' => $product->getSku(),
                    'name' => $product->getName(),
                    'quantity' => $quantity,
                    'unitPrice' => (float)$product->getPrice(),
                    'totalValue' => $value,
                ];
            }
        }

        // Sort by value descending
        usort($items, fn($a, $b) => $b['totalValue'] <=> $a['totalValue']);

        return [
            'generatedAt' => (new \DateTimeImmutable())->format('c'),
            'totalValue' => $totalValue,
            'itemCount' => count($items),
            'items' => $items,
        ];
    }

    /**
     * Get stock by location report
     */
    public function getStockByLocationReport(): array
    {
        $stocks = $this->stockRepository->findAll();
        $locations = [];

        foreach ($stocks as $stock) {
            $shelf = $stock->getShelf();
            $aisle = $shelf->getAisle();

            if (!isset($locations[$aisle])) {
                $locations[$aisle] = [
                    'aisle' => $aisle,
                    'totalQuantity' => 0,
                    'shelfCount' => 0,
                    'productCount' => 0,
                    'shelves' => [],
                ];
            }

            $shelfCode = $shelf->getCode();
            if (!isset($locations[$aisle]['shelves'][$shelfCode])) {
                $locations[$aisle]['shelves'][$shelfCode] = [
                    'code' => $shelfCode,
                    'location' => $shelf->getFullLocation(),
                    'quantity' => 0,
                    'products' => 0,
                ];
                $locations[$aisle]['shelfCount']++;
            }

            $locations[$aisle]['shelves'][$shelfCode]['quantity'] += $stock->getQuantity();
            $locations[$aisle]['shelves'][$shelfCode]['products']++;
            $locations[$aisle]['totalQuantity'] += $stock->getQuantity();
            $locations[$aisle]['productCount']++;
        }

        // Convert to arrays and sort
        $result = [];
        foreach ($locations as $aisle => $data) {
            $data['shelves'] = array_values($data['shelves']);
            $result[] = $data;
        }

        usort($result, fn($a, $b) => strcmp($a['aisle'], $b['aisle']));

        return [
            'generatedAt' => (new \DateTimeImmutable())->format('c'),
            'aisles' => $result,
        ];
    }
}

