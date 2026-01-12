<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ErpOrderDTO;
use App\DTO\ErpProductDTO;
use App\Service\ErpIntegrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/erp', name: 'erp_')]
class ErpIntegrationController extends AbstractController
{
    public function __construct(
        private readonly ErpIntegrationService $erpIntegrationService,
    ) {
    }

    #[Route('/products/sync', name: 'sync_product', methods: ['POST'])]
    public function syncProduct(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['sku']) || empty($data['barcode']) || empty($data['name'])) {
            return $this->json([
                'success' => false,
                'message' => 'SKU, barcode, and name are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = ErpProductDTO::fromArray($data);
        $result = $this->erpIntegrationService->syncProduct($dto);

        return $this->json($result, Response::HTTP_OK);
    }

    #[Route('/products/sync-batch', name: 'sync_products', methods: ['POST'])]
    public function syncProducts(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['products']) || !is_array($data['products'])) {
            return $this->json([
                'success' => false,
                'message' => 'Products array is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $dtos = array_map(fn($p) => ErpProductDTO::fromArray($p), $data['products']);
        $result = $this->erpIntegrationService->syncProducts($dtos);

        return $this->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    #[Route('/orders', name: 'create_order', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['externalOrderId']) && empty($data['order_id'])) {
            return $this->json([
                'success' => false,
                'message' => 'External order ID is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['customerName']) && empty($data['customer_name'])) {
            return $this->json([
                'success' => false,
                'message' => 'Customer name is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $dto = ErpOrderDTO::fromArray($data);
        $result = $this->erpIntegrationService->createOrderFromErp($dto);

        $statusCode = $result['success'] ? Response::HTTP_CREATED : Response::HTTP_CONFLICT;

        return $this->json($result, $statusCode);
    }

    #[Route('/orders/{externalOrderId}/status', name: 'order_status', methods: ['GET'])]
    public function orderStatus(string $externalOrderId): JsonResponse
    {
        $result = $this->erpIntegrationService->getOrderStatusForErp($externalOrderId);

        if (!$result) {
            return $this->json([
                'success' => false,
                'message' => 'Order not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    #[Route('/orders/{externalOrderId}/ship', name: 'ship_order', methods: ['POST'])]
    public function shipOrder(string $externalOrderId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $trackingNumber = $data['trackingNumber'] ?? $data['tracking_number'] ?? null;

        $result = $this->erpIntegrationService->markOrderShipped($externalOrderId, $trackingNumber);

        if (!$result['success']) {
            return $this->json($result, Response::HTTP_NOT_FOUND);
        }

        return $this->json($result);
    }

    #[Route('/stock-levels', name: 'stock_levels', methods: ['GET'])]
    public function stockLevels(): JsonResponse
    {
        $levels = $this->erpIntegrationService->getStockLevelsForErp();

        return $this->json([
            'success' => true,
            'count' => count($levels),
            'data' => $levels,
        ]);
    }

    #[Route('/webhook/test', name: 'webhook_test', methods: ['POST'])]
    public function webhookTest(Request $request): JsonResponse
    {
        // This endpoint can be used to test webhook configuration
        $payload = json_decode($request->getContent(), true) ?? [];

        return $this->json([
            'success' => true,
            'message' => 'Webhook received',
            'receivedAt' => (new \DateTimeImmutable())->format('c'),
            'payload' => $payload,
        ]);
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'healthy',
            'service' => 'WMS ERP Integration',
            'version' => '1.0.0',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ]);
    }
}

