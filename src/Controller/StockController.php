<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\ShelfRepository;
use App\Service\StockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/stock', name: 'stock_')]
class StockController extends AbstractController
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly ProductRepository $productRepository,
        private readonly ShelfRepository $shelfRepository,
    ) {
    }

    #[Route('/product/{id}', name: 'product', methods: ['GET'])]
    public function productStock(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return $this->json([
                'success' => false,
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $stock = $this->stockService->getProductStock($product);

        return $this->json([
            'success' => true,
            'data' => $stock,
        ]);
    }

    #[Route('/product/sku/{sku}', name: 'product_by_sku', methods: ['GET'])]
    public function productStockBySku(string $sku): JsonResponse
    {
        $product = $this->productRepository->findBySku($sku);

        if (!$product) {
            return $this->json([
                'success' => false,
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $stock = $this->stockService->getProductStock($product);

        return $this->json([
            'success' => true,
            'data' => $stock,
        ]);
    }

    #[Route('/update', name: 'update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['productId']) || empty($data['shelfCode']) || !isset($data['quantity'])) {
            return $this->json([
                'success' => false,
                'message' => 'Product ID, shelf code, and quantity are required',
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

        $result = $this->stockService->updateStock(
            $product,
            $shelf,
            (int)$data['quantity'],
            $data['type'] ?? 'adjustment',
            $data['reference'] ?? null,
            $data['notes'] ?? null
        );

        return $this->json([
            'success' => true,
            'message' => 'Stock updated',
            'data' => $result,
        ]);
    }

    #[Route('/adjust', name: 'adjust', methods: ['POST'])]
    public function adjust(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['productId']) || empty($data['shelfCode']) || !isset($data['newQuantity'])) {
            return $this->json([
                'success' => false,
                'message' => 'Product ID, shelf code, and new quantity are required',
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

        $result = $this->stockService->adjustStock(
            $product,
            $shelf,
            (int)$data['newQuantity'],
            $data['reason'] ?? null
        );

        return $this->json([
            'success' => true,
            'message' => 'Stock adjusted',
            'data' => $result,
        ]);
    }

    #[Route('/transfer', name: 'transfer', methods: ['POST'])]
    public function transfer(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['productId']) || empty($data['fromShelfCode']) || empty($data['toShelfCode']) || empty($data['quantity'])) {
            return $this->json([
                'success' => false,
                'message' => 'Product ID, from/to shelf codes, and quantity are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $product = $this->productRepository->find($data['productId']);
        if (!$product) {
            return $this->json([
                'success' => false,
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $fromShelf = $this->shelfRepository->findByCode($data['fromShelfCode']);
        $toShelf = $this->shelfRepository->findByCode($data['toShelfCode']);

        if (!$fromShelf || !$toShelf) {
            return $this->json([
                'success' => false,
                'message' => 'Shelf not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $result = $this->stockService->transferStock(
            $product,
            $fromShelf,
            $toShelf,
            (int)$data['quantity'],
            $data['reference'] ?? null
        );

        if (!$result['success']) {
            return $this->json($result, Response::HTTP_CONFLICT);
        }

        return $this->json([
            'success' => true,
            'message' => 'Stock transferred',
            'data' => $result,
        ]);
    }

    #[Route('/summary', name: 'summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        $summary = $this->stockService->getInventorySummary();

        return $this->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    #[Route('/movements', name: 'movements', methods: ['GET'])]
    public function movements(Request $request): JsonResponse
    {
        $limit = (int)($request->query->get('limit', 50));
        $movements = $this->stockService->getRecentMovements($limit);

        return $this->json([
            'success' => true,
            'data' => $movements,
        ]);
    }

    #[Route('/product/{id}/movements', name: 'product_movements', methods: ['GET'])]
    public function productMovements(int $id, Request $request): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return $this->json([
                'success' => false,
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $limit = (int)($request->query->get('limit', 50));
        $movements = $this->stockService->getProductMovements($product, $limit);

        return $this->json([
            'success' => true,
            'data' => $movements,
        ]);
    }
}

