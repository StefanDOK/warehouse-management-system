<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PickList;
use App\Repository\OrderRepository;
use App\Service\PickListService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/pick-lists', name: 'pick_list_')]
class PickListController extends AbstractController
{
    public function __construct(
        private readonly PickListService $pickListService,
        private readonly OrderRepository $orderRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');

        $pickLists = match ($status) {
            'pending' => $this->pickListService->getPendingPickLists(),
            'in_progress' => $this->pickListService->getInProgressPickLists(),
            default => array_merge(
                $this->pickListService->getPendingPickLists(),
                $this->pickListService->getInProgressPickLists()
            ),
        };

        return $this->json([
            'success' => true,
            'data' => array_map(fn(PickList $pl) => $this->serializePickList($pl), $pickLists),
        ]);
    }

    #[Route('/generate', name: 'generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['orderId'])) {
            return $this->json([
                'success' => false,
                'message' => 'Order ID is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $order = $this->orderRepository->find($data['orderId']);

        if (!$order) {
            return $this->json([
                'success' => false,
                'message' => 'Order not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $pickList = $this->pickListService->generatePickList($order);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => 'Pick list generated successfully',
            'data' => $this->serializePickList($pickList, true),
        ], Response::HTTP_CREATED);
    }

    #[Route('/generate-batch', name: 'generate_batch', methods: ['POST'])]
    public function generateBatch(): JsonResponse
    {
        $pendingOrders = $this->orderRepository->findPending();

        $pickLists = $this->pickListService->generatePickListsBatch($pendingOrders);

        return $this->json([
            'success' => true,
            'message' => sprintf('Generated %d pick lists', count($pickLists)),
            'data' => array_map(fn(PickList $pl) => $this->serializePickList($pl), $pickLists),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $pickList = $this->pickListService->getPickList($id);

        if (!$pickList) {
            return $this->json([
                'success' => false,
                'message' => 'Pick list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializePickList($pickList, true),
        ]);
    }

    #[Route('/{id}/optimized-path', name: 'optimized_path', methods: ['GET'])]
    public function optimizedPath(int $id): JsonResponse
    {
        $pickList = $this->pickListService->getPickList($id);

        if (!$pickList) {
            return $this->json([
                'success' => false,
                'message' => 'Pick list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $items = $this->pickListService->getOptimizedPickingPath($pickList);

        return $this->json([
            'success' => true,
            'data' => [
                'pickListNumber' => $pickList->getPickListNumber(),
                'items' => array_map(fn($item) => [
                    'id' => $item->getId(),
                    'product' => [
                        'id' => $item->getProduct()->getId(),
                        'sku' => $item->getProduct()->getSku(),
                        'name' => $item->getProduct()->getName(),
                        'barcode' => $item->getProduct()->getBarcode(),
                    ],
                    'location' => $item->getShelfLocation(),
                    'quantity' => $item->getQuantity(),
                    'pickedQuantity' => $item->getPickedQuantity(),
                    'isPicked' => $item->isPicked(),
                ], $items),
            ],
        ]);
    }

    #[Route('/{id}/start', name: 'start', methods: ['POST'])]
    public function start(int $id): JsonResponse
    {
        $pickList = $this->pickListService->getPickList($id);

        if (!$pickList) {
            return $this->json([
                'success' => false,
                'message' => 'Pick list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->pickListService->startPicking($pickList);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => 'Picking started',
            'data' => $this->serializePickList($pickList),
        ]);
    }

    #[Route('/{id}/complete', name: 'complete', methods: ['POST'])]
    public function complete(int $id): JsonResponse
    {
        $pickList = $this->pickListService->getPickList($id);

        if (!$pickList) {
            return $this->json([
                'success' => false,
                'message' => 'Pick list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->pickListService->completePicking($pickList);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => 'Picking completed',
            'data' => $this->serializePickList($pickList),
        ]);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        $pickList = $this->pickListService->getPickList($id);

        if (!$pickList) {
            return $this->json([
                'success' => false,
                'message' => 'Pick list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->pickListService->cancelPickList($pickList);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => 'Pick list cancelled',
            'data' => $this->serializePickList($pickList),
        ]);
    }

    private function serializePickList(PickList $pickList, bool $includeItems = false): array
    {
        $data = [
            'id' => $pickList->getId(),
            'pickListNumber' => $pickList->getPickListNumber(),
            'status' => $pickList->getStatus(),
            'order' => [
                'id' => $pickList->getOrder()?->getId(),
                'orderNumber' => $pickList->getOrder()?->getOrderNumber(),
                'customerName' => $pickList->getOrder()?->getCustomerName(),
            ],
            'createdAt' => $pickList->getCreatedAt()->format('c'),
            'startedAt' => $pickList->getStartedAt()?->format('c'),
            'completedAt' => $pickList->getCompletedAt()?->format('c'),
            'progress' => $pickList->getProgress(),
            'itemCount' => $pickList->getItems()->count(),
        ];

        if ($includeItems) {
            $data['items'] = [];
            foreach ($pickList->getItems() as $item) {
                $data['items'][] = [
                    'id' => $item->getId(),
                    'product' => [
                        'id' => $item->getProduct()->getId(),
                        'sku' => $item->getProduct()->getSku(),
                        'name' => $item->getProduct()->getName(),
                        'barcode' => $item->getProduct()->getBarcode(),
                    ],
                    'location' => $item->getShelfLocation(),
                    'quantity' => $item->getQuantity(),
                    'pickedQuantity' => $item->getPickedQuantity(),
                    'isPicked' => $item->isPicked(),
                    'pickedAt' => $item->getPickedAt()?->format('c'),
                ];
            }
        }

        return $data;
    }
}

