<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ProductReturn;
use App\Entity\ProductReturnItem;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\ProductReturnService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/returns', name: 'returns_')]
class ProductReturnController extends AbstractController
{
    public function __construct(
        private readonly ProductReturnService $productReturnService,
        private readonly ProductRepository $productRepository,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $returns = $this->productReturnService->getRecentReturns();

        return $this->json([
            'success' => true,
            'data' => array_map(fn(ProductReturn $r) => $this->serializeReturn($r), $returns),
        ]);
    }

    #[Route('/pending', name: 'pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        $returns = $this->productReturnService->getPendingReturns();

        return $this->json([
            'success' => true,
            'data' => array_map(fn(ProductReturn $r) => $this->serializeReturn($r), $returns),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['reason'])) {
            return $this->json([
                'success' => false,
                'message' => 'Reason is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $order = null;
        if (!empty($data['orderId'])) {
            $order = $this->orderRepository->find($data['orderId']);
        }

        $return = $this->productReturnService->createReturn(
            $data['reason'],
            $order,
            $data['notes'] ?? null
        );

        return $this->json([
            'success' => true,
            'message' => 'Return created successfully',
            'data' => $this->serializeReturn($return),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $return = $this->productReturnService->getReturn($id);

        if (!$return) {
            return $this->json([
                'success' => false,
                'message' => 'Return not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeReturn($return, true),
        ]);
    }

    #[Route('/{id}/items', name: 'add_item', methods: ['POST'])]
    public function addItem(int $id, Request $request): JsonResponse
    {
        $return = $this->productReturnService->getReturn($id);

        if (!$return) {
            return $this->json([
                'success' => false,
                'message' => 'Return not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['productId']) || empty($data['quantity'])) {
            return $this->json([
                'success' => false,
                'message' => 'Product ID and quantity are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $product = $this->productRepository->find($data['productId']);
        if (!$product) {
            return $this->json([
                'success' => false,
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $item = $this->productReturnService->addItem(
            $return,
            $product,
            (int)$data['quantity'],
            $data['condition'] ?? ProductReturnItem::CONDITION_GOOD,
            $data['notes'] ?? null
        );

        return $this->json([
            'success' => true,
            'message' => 'Item added to return',
            'data' => [
                'id' => $item->getId(),
                'product' => [
                    'id' => $product->getId(),
                    'sku' => $product->getSku(),
                    'name' => $product->getName(),
                ],
                'quantity' => $item->getQuantity(),
                'condition' => $item->getCondition(),
                'restockable' => $item->isRestockable(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/inspect', name: 'inspect', methods: ['POST'])]
    public function startInspection(int $id): JsonResponse
    {
        $return = $this->productReturnService->getReturn($id);

        if (!$return) {
            return $this->json([
                'success' => false,
                'message' => 'Return not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->productReturnService->startInspection($return);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => 'Inspection started',
            'data' => $this->serializeReturn($return),
        ]);
    }

    #[Route('/{id}/complete', name: 'complete', methods: ['POST'])]
    public function complete(int $id): JsonResponse
    {
        $return = $this->productReturnService->getReturn($id);

        if (!$return) {
            return $this->json([
                'success' => false,
                'message' => 'Return not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $results = $this->productReturnService->completeAndRestock($return);

        return $this->json([
            'success' => true,
            'message' => 'Return completed and items restocked',
            'data' => [
                'return' => $this->serializeReturn($return),
                'restockResults' => $results,
            ],
        ]);
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    public function reject(int $id, Request $request): JsonResponse
    {
        $return = $this->productReturnService->getReturn($id);

        if (!$return) {
            return $this->json([
                'success' => false,
                'message' => 'Return not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $this->productReturnService->rejectReturn($return, $data['notes'] ?? null);

        return $this->json([
            'success' => true,
            'message' => 'Return rejected',
            'data' => $this->serializeReturn($return),
        ]);
    }

    private function serializeReturn(ProductReturn $return, bool $includeItems = false): array
    {
        $data = [
            'id' => $return->getId(),
            'returnNumber' => $return->getReturnNumber(),
            'reason' => $return->getReason(),
            'status' => $return->getStatus(),
            'originalOrder' => $return->getOriginalOrder() ? [
                'id' => $return->getOriginalOrder()->getId(),
                'orderNumber' => $return->getOriginalOrder()->getOrderNumber(),
            ] : null,
            'notes' => $return->getNotes(),
            'createdAt' => $return->getCreatedAt()->format('c'),
            'completedAt' => $return->getCompletedAt()?->format('c'),
            'totalItems' => $return->getTotalItems(),
            'itemCount' => $return->getItems()->count(),
        ];

        if ($includeItems) {
            $data['items'] = [];
            foreach ($return->getItems() as $item) {
                $data['items'][] = [
                    'id' => $item->getId(),
                    'product' => [
                        'id' => $item->getProduct()->getId(),
                        'sku' => $item->getProduct()->getSku(),
                        'name' => $item->getProduct()->getName(),
                    ],
                    'quantity' => $item->getQuantity(),
                    'condition' => $item->getCondition(),
                    'restockable' => $item->isRestockable(),
                    'restocked' => $item->isRestocked(),
                    'allocatedShelf' => $item->getAllocatedShelf()?->getCode(),
                    'notes' => $item->getNotes(),
                ];
            }
        }

        return $data;
    }
}

