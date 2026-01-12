<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\GoodsReceiptRepository;
use App\Repository\ProductRepository;
use App\Repository\ShelfRepository;
use App\Service\StockAllocationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/stock-allocation', name: 'stock_allocation_')]
class StockAllocationController extends AbstractController
{
    public function __construct(
        private readonly StockAllocationService $stockAllocationService,
        private readonly ProductRepository $productRepository,
        private readonly ShelfRepository $shelfRepository,
        private readonly GoodsReceiptRepository $goodsReceiptRepository,
    ) {
    }

    #[Route('/allocate', name: 'allocate', methods: ['POST'])]
    public function allocate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['productId']) && empty($data['sku'])) {
            return $this->json([
                'success' => false,
                'message' => 'Product ID or SKU is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['quantity']) || $data['quantity'] < 1) {
            return $this->json([
                'success' => false,
                'message' => 'Quantity must be at least 1',
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

        $result = $this->stockAllocationService->allocateToShelf(
            $product,
            (int)$data['quantity'],
            $data['reference'] ?? null
        );

        if (!$result) {
            return $this->json([
                'success' => false,
                'message' => 'No available shelf space for allocation',
            ], Response::HTTP_CONFLICT);
        }

        unset($result['shelf']);

        return $this->json([
            'success' => true,
            'message' => 'Stock allocated successfully',
            'data' => $result,
        ]);
    }

    #[Route('/allocate-to-shelf', name: 'allocate_to_shelf', methods: ['POST'])]
    public function allocateToShelf(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['productId'])) {
            return $this->json([
                'success' => false,
                'message' => 'Product ID is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['shelfCode'])) {
            return $this->json([
                'success' => false,
                'message' => 'Shelf code is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['quantity']) || $data['quantity'] < 1) {
            return $this->json([
                'success' => false,
                'message' => 'Quantity must be at least 1',
            ], Response::HTTP_BAD_REQUEST);
        }

        $product = $this->productRepository->find($data['productId']);
        if (!$product) {
            return $this->json([
                'success' => false,
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $shelf = $this->shelfRepository->findByCode($data['shelfCode']);
        if (!$shelf) {
            return $this->json([
                'success' => false,
                'message' => 'Shelf not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $result = $this->stockAllocationService->allocateToSpecificShelf(
            $product,
            $shelf,
            (int)$data['quantity'],
            $data['reference'] ?? null
        );

        if (!$result['success']) {
            return $this->json($result, Response::HTTP_CONFLICT);
        }

        unset($result['shelf']);

        return $this->json([
            'success' => true,
            'message' => 'Stock allocated to shelf successfully',
            'data' => $result,
        ]);
    }

    #[Route('/suggest', name: 'suggest', methods: ['POST'])]
    public function suggest(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['productId'])) {
            return $this->json([
                'success' => false,
                'message' => 'Product ID is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $product = $this->productRepository->find($data['productId']);
        if (!$product) {
            return $this->json([
                'success' => false,
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $quantity = (int)($data['quantity'] ?? 1);
        $suggestion = $this->stockAllocationService->suggestShelf($product, $quantity);

        if (!$suggestion) {
            return $this->json([
                'success' => false,
                'message' => 'No available shelf for this quantity',
            ]);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'shelfCode' => $suggestion['shelf']->getCode(),
                'shelfLocation' => $suggestion['shelf']->getFullLocation(),
                'reason' => $suggestion['reason'],
                'currentQuantity' => $suggestion['currentQuantity'],
                'availableSpace' => $suggestion['availableSpace'],
            ],
        ]);
    }

    #[Route('/from-goods-receipt/{id}', name: 'from_goods_receipt', methods: ['POST'])]
    public function allocateFromGoodsReceipt(int $id): JsonResponse
    {
        $goodsReceipt = $this->goodsReceiptRepository->find($id);

        if (!$goodsReceipt) {
            return $this->json([
                'success' => false,
                'message' => 'Goods receipt not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $results = $this->stockAllocationService->allocateFromGoodsReceipt($goodsReceipt);

        return $this->json([
            'success' => true,
            'message' => 'Stock allocation completed',
            'data' => $results,
        ]);
    }

    #[Route('/shelves/available', name: 'available_shelves', methods: ['GET'])]
    public function availableShelves(Request $request): JsonResponse
    {
        $capacity = (int)($request->query->get('capacity', 1));
        $shelves = $this->shelfRepository->findAvailableShelves($capacity);

        return $this->json([
            'success' => true,
            'data' => array_map(fn($s) => [
                'id' => $s->getId(),
                'code' => $s->getCode(),
                'location' => $s->getFullLocation(),
                'aisle' => $s->getAisle(),
                'rack' => $s->getRack(),
                'level' => $s->getLevel(),
                'maxCapacity' => $s->getMaxCapacity(),
                'currentOccupancy' => $s->getCurrentOccupancy(),
                'availableCapacity' => $s->getAvailableCapacity(),
            ], $shelves),
        ]);
    }
}

