<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ErpOrderDTO;
use App\DTO\ErpProductDTO;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;

class ErpIntegrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
        private readonly OrderRepository $orderRepository,
        private readonly StockRepository $stockRepository,
    ) {
    }

    /**
     * Sync product from ERP
     */
    public function syncProduct(ErpProductDTO $dto): array
    {
        $product = $this->productRepository->findBySku($dto->sku);
        $isNew = false;

        if (!$product) {
            $product = new Product();
            $product->setSku($dto->sku);
            $isNew = true;
        }

        $product->setBarcode($dto->barcode);
        $product->setName($dto->name);
        $product->setDescription($dto->description);
        $product->setPrice($dto->price);
        $product->setMinStockLevel($dto->minStockLevel);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return [
            'success' => true,
            'action' => $isNew ? 'created' : 'updated',
            'product' => [
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
            ],
        ];
    }

    /**
     * Sync multiple products from ERP
     * @param ErpProductDTO[] $products
     */
    public function syncProducts(array $products): array
    {
        $results = [
            'total' => count($products),
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'details' => [],
        ];

        foreach ($products as $dto) {
            try {
                $result = $this->syncProduct($dto);
                if ($result['action'] === 'created') {
                    $results['created']++;
                } else {
                    $results['updated']++;
                }
                $results['details'][] = $result;
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'success' => false,
                    'sku' => $dto->sku,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Create order from ERP
     */
    public function createOrderFromErp(ErpOrderDTO $dto): array
    {
        // Check if order already exists
        $existingOrder = $this->orderRepository->findByExternalOrderId($dto->externalOrderId);
        if ($existingOrder) {
            return [
                'success' => false,
                'message' => 'Order already exists',
                'order' => [
                    'id' => $existingOrder->getId(),
                    'orderNumber' => $existingOrder->getOrderNumber(),
                    'externalOrderId' => $existingOrder->getExternalOrderId(),
                ],
            ];
        }

        $order = new Order();
        $order->setExternalOrderId($dto->externalOrderId);
        $order->setCustomerName($dto->customerName);
        $order->setShippingAddress($dto->shippingAddress);
        $order->setPriority($dto->priority);

        $itemsAdded = 0;
        $itemsSkipped = 0;

        foreach ($dto->items as $itemData) {
            $sku = $itemData['sku'] ?? null;
            $quantity = (int)($itemData['quantity'] ?? 1);
            $unitPrice = (string)($itemData['unitPrice'] ?? $itemData['unit_price'] ?? '0.00');

            if (!$sku) {
                $itemsSkipped++;
                continue;
            }

            $product = $this->productRepository->findBySku($sku);
            if (!$product) {
                $itemsSkipped++;
                continue;
            }

            $orderItem = new OrderItem();
            $orderItem->setProduct($product);
            $orderItem->setQuantity($quantity);
            $orderItem->setUnitPrice($unitPrice);

            $order->addItem($orderItem);
            $itemsAdded++;
        }

        if ($itemsAdded === 0) {
            return [
                'success' => false,
                'message' => 'No valid items in order',
                'itemsSkipped' => $itemsSkipped,
            ];
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return [
            'success' => true,
            'message' => 'Order created successfully',
            'order' => [
                'id' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'externalOrderId' => $order->getExternalOrderId(),
                'status' => $order->getStatus(),
                'itemCount' => $order->getItems()->count(),
            ],
            'itemsAdded' => $itemsAdded,
            'itemsSkipped' => $itemsSkipped,
        ];
    }

    /**
     * Get stock levels for ERP
     */
    public function getStockLevelsForErp(): array
    {
        $summary = $this->stockRepository->getInventorySummary();

        return array_map(fn($item) => [
            'sku' => $item['sku'],
            'name' => $item['name'],
            'totalQuantity' => (int)$item['totalQuantity'],
            'availableQuantity' => (int)$item['availableQuantity'],
        ], $summary);
    }

    /**
     * Get order status for ERP
     */
    public function getOrderStatusForErp(string $externalOrderId): ?array
    {
        $order = $this->orderRepository->findByExternalOrderId($externalOrderId);

        if (!$order) {
            return null;
        }

        return [
            'externalOrderId' => $order->getExternalOrderId(),
            'wmsOrderNumber' => $order->getOrderNumber(),
            'status' => $order->getStatus(),
            'createdAt' => $order->getCreatedAt()->format('c'),
            'shippedAt' => $order->getShippedAt()?->format('c'),
            'items' => array_map(fn($item) => [
                'sku' => $item->getProduct()->getSku(),
                'quantity' => $item->getQuantity(),
            ], $order->getItems()->toArray()),
        ];
    }

    /**
     * Update order status from shipment
     */
    public function markOrderShipped(string $externalOrderId, ?string $trackingNumber = null): array
    {
        $order = $this->orderRepository->findByExternalOrderId($externalOrderId);

        if (!$order) {
            return [
                'success' => false,
                'message' => 'Order not found',
            ];
        }

        $order->ship();
        $this->entityManager->flush();

        return [
            'success' => true,
            'message' => 'Order marked as shipped',
            'order' => [
                'externalOrderId' => $order->getExternalOrderId(),
                'wmsOrderNumber' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'shippedAt' => $order->getShippedAt()?->format('c'),
            ],
        ];
    }

    /**
     * Webhook for order status updates to ERP
     */
    public function prepareOrderStatusWebhook(Order $order): array
    {
        return [
            'event' => 'order.status.updated',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'data' => [
                'externalOrderId' => $order->getExternalOrderId(),
                'wmsOrderNumber' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'shippedAt' => $order->getShippedAt()?->format('c'),
            ],
        ];
    }

    /**
     * Webhook for low stock alerts to ERP
     */
    public function prepareLowStockWebhook(Product $product, int $currentStock): array
    {
        return [
            'event' => 'stock.low',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'data' => [
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'currentStock' => $currentStock,
                'minStockLevel' => $product->getMinStockLevel(),
                'deficit' => $product->getMinStockLevel() - $currentStock,
            ],
        ];
    }
}

