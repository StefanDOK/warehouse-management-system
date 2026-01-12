<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\GoodsReceiptItemRepository;
use App\Service\BarcodeScannerService;
use App\Service\GoodsReceiptService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/barcode', name: 'barcode_')]
class BarcodeScanController extends AbstractController
{
    public function __construct(
        private readonly BarcodeScannerService $barcodeScannerService,
        private readonly GoodsReceiptService $goodsReceiptService,
        private readonly GoodsReceiptItemRepository $goodsReceiptItemRepository,
    ) {
    }

    #[Route('/scan', name: 'scan', methods: ['POST'])]
    public function scan(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['barcode'])) {
            return $this->json([
                'success' => false,
                'message' => 'Barcode is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->barcodeScannerService->quickScan($data['barcode']);

        return $this->json($result, $result['success'] ? Response::HTTP_OK : Response::HTTP_NOT_FOUND);
    }

    #[Route('/batch-scan', name: 'batch_scan', methods: ['POST'])]
    public function batchScan(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['barcodes']) || !is_array($data['barcodes'])) {
            return $this->json([
                'success' => false,
                'message' => 'Barcodes array is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $results = $this->barcodeScannerService->batchScan($data['barcodes']);

        $found = 0;
        $notFound = 0;
        foreach ($results as $result) {
            if ($result['success']) {
                $found++;
            } else {
                $notFound++;
            }
        }

        return $this->json([
            'success' => true,
            'summary' => [
                'total' => count($data['barcodes']),
                'found' => $found,
                'notFound' => $notFound,
            ],
            'results' => $results,
        ]);
    }

    #[Route('/goods-receipt/{receiptId}/items/{itemId}/scan', name: 'goods_receipt_scan', methods: ['POST'])]
    public function scanAtGoodsReceipt(int $receiptId, int $itemId, Request $request): JsonResponse
    {
        $receipt = $this->goodsReceiptService->getReceipt($receiptId);

        if (!$receipt) {
            return $this->json([
                'success' => false,
                'message' => 'Goods receipt not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $item = $this->goodsReceiptItemRepository->find($itemId);

        if (!$item || $item->getGoodsReceipt()?->getId() !== $receiptId) {
            return $this->json([
                'success' => false,
                'message' => 'Item not found in this goods receipt',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['barcode'])) {
            return $this->json([
                'success' => false,
                'message' => 'Barcode is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
        if ($quantity < 1) {
            $quantity = 1;
        }

        $result = $this->barcodeScannerService->scanAtGoodsReceipt($item, $data['barcode'], $quantity);

        return $this->json($result, $result['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['barcode'])) {
            return $this->json([
                'success' => false,
                'message' => 'Barcode is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $product = $this->barcodeScannerService->validateBarcode($data['barcode']);

        if (!$product) {
            return $this->json([
                'success' => false,
                'valid' => false,
                'message' => 'Invalid barcode - product not found',
                'barcode' => $data['barcode'],
            ]);
        }

        return $this->json([
            'success' => true,
            'valid' => true,
            'message' => 'Barcode is valid',
            'barcode' => $data['barcode'],
            'product' => [
                'id' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
            ],
        ]);
    }
}

