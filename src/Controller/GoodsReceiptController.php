<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GoodsReceipt;
use App\Repository\ProductRepository;
use App\Service\GoodsReceiptService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/goods-receipts', name: 'goods_receipt_')]
class GoodsReceiptController extends AbstractController
{
    public function __construct(
        private readonly GoodsReceiptService $goodsReceiptService,
        private readonly ProductRepository $productRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $receipts = $this->goodsReceiptService->getRecentReceipts(50);

        return $this->json([
            'success' => true,
            'data' => array_map(fn(GoodsReceipt $r) => $this->serializeReceipt($r), $receipts),
        ]);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'data' => $this->goodsReceiptService->getReceiptStats(),
        ]);
    }

    #[Route('/pending', name: 'pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        $receipts = $this->goodsReceiptService->getPendingReceipts();

        return $this->json([
            'success' => true,
            'data' => array_map(fn(GoodsReceipt $r) => $this->serializeReceipt($r), $receipts),
        ]);
    }

    #[Route('/in-progress', name: 'in_progress', methods: ['GET'])]
    public function inProgress(): JsonResponse
    {
        $receipts = $this->goodsReceiptService->getInProgressReceipts();

        return $this->json([
            'success' => true,
            'data' => array_map(fn(GoodsReceipt $r) => $this->serializeReceipt($r), $receipts),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $receipt = $this->goodsReceiptService->createGoodsReceipt(
            $data['supplierName'] ?? null,
            $data['purchaseOrderNumber'] ?? null,
            $data['notes'] ?? null
        );

        return $this->json([
            'success' => true,
            'message' => 'Goods receipt created successfully',
            'data' => $this->serializeReceipt($receipt),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $receipt = $this->goodsReceiptService->getReceipt($id);

        if (!$receipt) {
            return $this->json([
                'success' => false,
                'message' => 'Goods receipt not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeReceipt($receipt, true),
        ]);
    }

    #[Route('/{id}/items', name: 'add_item', methods: ['POST'])]
    public function addItem(int $id, Request $request): JsonResponse
    {
        $receipt = $this->goodsReceiptService->getReceipt($id);

        if (!$receipt) {
            return $this->json([
                'success' => false,
                'message' => 'Goods receipt not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['productId']) && empty($data['sku'])) {
            return $this->json([
                'success' => false,
                'message' => 'Product ID or SKU is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['expectedQuantity']) || $data['expectedQuantity'] < 1) {
            return $this->json([
                'success' => false,
                'message' => 'Expected quantity must be at least 1',
            ], Response::HTTP_BAD_REQUEST);
        }

        $product = null;
        if (!empty($data['productId'])) {
            $product = $this->productRepository->find($data['productId']);
        } elseif (!empty($data['sku'])) {
            $product = $this->productRepository->findBySku($data['sku']);
        }

        if (!$product) {
            return $this->json([
                'success' => false,
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $item = $this->goodsReceiptService->addItem($receipt, $product, (int)$data['expectedQuantity']);

        return $this->json([
            'success' => true,
            'message' => 'Item added successfully',
            'data' => [
                'id' => $item->getId(),
                'product' => [
                    'id' => $product->getId(),
                    'sku' => $product->getSku(),
                    'name' => $product->getName(),
                ],
                'expectedQuantity' => $item->getExpectedQuantity(),
                'receivedQuantity' => $item->getReceivedQuantity(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/start', name: 'start', methods: ['POST'])]
    public function start(int $id): JsonResponse
    {
        $receipt = $this->goodsReceiptService->getReceipt($id);

        if (!$receipt) {
            return $this->json([
                'success' => false,
                'message' => 'Goods receipt not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->goodsReceiptService->startProcessing($receipt);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => 'Goods receipt processing started',
            'data' => $this->serializeReceipt($receipt),
        ]);
    }

    #[Route('/{id}/complete', name: 'complete', methods: ['POST'])]
    public function complete(int $id): JsonResponse
    {
        $receipt = $this->goodsReceiptService->getReceipt($id);

        if (!$receipt) {
            return $this->json([
                'success' => false,
                'message' => 'Goods receipt not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->goodsReceiptService->completeReceipt($receipt);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => 'Goods receipt completed',
            'data' => $this->serializeReceipt($receipt),
        ]);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        $receipt = $this->goodsReceiptService->getReceipt($id);

        if (!$receipt) {
            return $this->json([
                'success' => false,
                'message' => 'Goods receipt not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->goodsReceiptService->cancelReceipt($receipt);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => 'Goods receipt cancelled',
            'data' => $this->serializeReceipt($receipt),
        ]);
    }

    private function serializeReceipt(GoodsReceipt $receipt, bool $includeItems = false): array
    {
        $data = [
            'id' => $receipt->getId(),
            'receiptNumber' => $receipt->getReceiptNumber(),
            'supplierName' => $receipt->getSupplierName(),
            'purchaseOrderNumber' => $receipt->getPurchaseOrderNumber(),
            'status' => $receipt->getStatus(),
            'notes' => $receipt->getNotes(),
            'createdAt' => $receipt->getCreatedAt()->format('c'),
            'completedAt' => $receipt->getCompletedAt()?->format('c'),
            'totalItems' => $receipt->getTotalItems(),
            'itemCount' => $receipt->getItems()->count(),
        ];

        if ($includeItems) {
            $data['items'] = [];
            foreach ($receipt->getItems() as $item) {
                $data['items'][] = [
                    'id' => $item->getId(),
                    'product' => [
                        'id' => $item->getProduct()->getId(),
                        'sku' => $item->getProduct()->getSku(),
                        'name' => $item->getProduct()->getName(),
                        'barcode' => $item->getProduct()->getBarcode(),
                    ],
                    'expectedQuantity' => $item->getExpectedQuantity(),
                    'receivedQuantity' => $item->getReceivedQuantity(),
                    'scannedBarcode' => $item->getScannedBarcode(),
                    'scannedAt' => $item->getScannedAt()?->format('c'),
                    'allocatedShelf' => $item->getAllocatedShelf()?->getCode(),
                    'isFullyReceived' => $item->isFullyReceived(),
                    'discrepancy' => $item->getDiscrepancy(),
                ];
            }
        }

        return $data;
    }
}

