<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PickListItemRepository;
use App\Service\PickingScanService;
use App\Service\PickListService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/picking', name: 'picking_')]
class PickingScanController extends AbstractController
{
    public function __construct(
        private readonly PickingScanService $pickingScanService,
        private readonly PickListService $pickListService,
        private readonly PickListItemRepository $pickListItemRepository,
    ) {
    }

    #[Route('/{pickListId}/scan', name: 'scan', methods: ['POST'])]
    public function scan(int $pickListId, Request $request): JsonResponse
    {
        $pickList = $this->pickListService->getPickList($pickListId);

        if (!$pickList) {
            return $this->json([
                'success' => false,
                'message' => 'Pick list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['barcode'])) {
            return $this->json([
                'success' => false,
                'message' => 'Barcode is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $quantity = (int)($data['quantity'] ?? 1);
        if ($quantity < 1) {
            $quantity = 1;
        }

        $result = $this->pickingScanService->quickScanAndPick($pickList, $data['barcode'], $quantity);

        return $this->json($result, $result['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    #[Route('/{pickListId}/items/{itemId}/scan', name: 'scan_item', methods: ['POST'])]
    public function scanItem(int $pickListId, int $itemId, Request $request): JsonResponse
    {
        $pickList = $this->pickListService->getPickList($pickListId);

        if (!$pickList) {
            return $this->json([
                'success' => false,
                'message' => 'Pick list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $item = $this->pickListItemRepository->find($itemId);

        if (!$item || $item->getPickList()?->getId() !== $pickListId) {
            return $this->json([
                'success' => false,
                'message' => 'Item not found in this pick list',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['barcode'])) {
            return $this->json([
                'success' => false,
                'message' => 'Barcode is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $quantity = (int)($data['quantity'] ?? 1);
        if ($quantity < 1) {
            $quantity = 1;
        }

        // Check if location validation is required
        if (!empty($data['locationCode'])) {
            $result = $this->pickingScanService->scanWithLocationValidation(
                $item,
                $data['barcode'],
                $data['locationCode'],
                $quantity
            );
        } else {
            $result = $this->pickingScanService->scanItem($item, $data['barcode'], $quantity);
        }

        return $this->json($result, $result['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    #[Route('/{pickListId}/items/{itemId}/pick-all', name: 'pick_all', methods: ['POST'])]
    public function pickAll(int $pickListId, int $itemId, Request $request): JsonResponse
    {
        $pickList = $this->pickListService->getPickList($pickListId);

        if (!$pickList) {
            return $this->json([
                'success' => false,
                'message' => 'Pick list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $item = $this->pickListItemRepository->find($itemId);

        if (!$item || $item->getPickList()?->getId() !== $pickListId) {
            return $this->json([
                'success' => false,
                'message' => 'Item not found in this pick list',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['barcode'])) {
            return $this->json([
                'success' => false,
                'message' => 'Barcode is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->pickingScanService->scanAndPickAll($item, $data['barcode']);

        return $this->json($result, $result['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    #[Route('/{pickListId}/progress', name: 'progress', methods: ['GET'])]
    public function progress(int $pickListId): JsonResponse
    {
        $pickList = $this->pickListService->getPickList($pickListId);

        if (!$pickList) {
            return $this->json([
                'success' => false,
                'message' => 'Pick list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $progress = $this->pickingScanService->getPickingProgress($pickList);

        return $this->json([
            'success' => true,
            'data' => $progress,
        ]);
    }

    #[Route('/{pickListId}/validate-location', name: 'validate_location', methods: ['POST'])]
    public function validateLocation(int $pickListId, Request $request): JsonResponse
    {
        $pickList = $this->pickListService->getPickList($pickListId);

        if (!$pickList) {
            return $this->json([
                'success' => false,
                'message' => 'Pick list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['itemId']) || empty($data['locationCode'])) {
            return $this->json([
                'success' => false,
                'message' => 'Item ID and location code are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $item = $this->pickListItemRepository->find($data['itemId']);

        if (!$item || $item->getPickList()?->getId() !== $pickListId) {
            return $this->json([
                'success' => false,
                'message' => 'Item not found in this pick list',
            ], Response::HTTP_NOT_FOUND);
        }

        $isValid = $this->pickingScanService->validateLocation($item, $data['locationCode']);

        return $this->json([
            'success' => true,
            'valid' => $isValid,
            'expectedLocation' => $item->getShelfLocation(),
            'scannedLocation' => $data['locationCode'],
        ]);
    }
}

