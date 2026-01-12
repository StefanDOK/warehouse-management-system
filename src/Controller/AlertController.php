<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LowStockAlert;
use App\Service\LowStockAlertService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/alerts', name: 'alerts_')]
class AlertController extends AbstractController
{
    public function __construct(
        private readonly LowStockAlertService $alertService,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $severity = $request->query->get('severity');

        if ($severity) {
            $alerts = $this->alertService->getAlertsBySeverity($severity);
        } else {
            $alerts = $this->alertService->getActiveAlerts();
        }

        return $this->json([
            'success' => true,
            'count' => count($alerts),
            'data' => array_map(fn(LowStockAlert $a) => $this->serializeAlert($a), $alerts),
        ]);
    }

    #[Route('/summary', name: 'summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        $summary = $this->alertService->getAlertsSummary();

        return $this->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    #[Route('/check', name: 'check', methods: ['POST'])]
    public function check(): JsonResponse
    {
        $result = $this->alertService->checkAllProducts();

        return $this->json([
            'success' => true,
            'message' => sprintf(
                'Checked %d products. Created %d new alerts, resolved %d alerts.',
                $result['checked'],
                $result['newAlerts'],
                $result['resolvedAlerts']
            ),
            'data' => $result,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $alert = $this->alertService->getAlert($id);

        if (!$alert) {
            return $this->json([
                'success' => false,
                'message' => 'Alert not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'success' => true,
            'data' => $this->serializeAlert($alert),
        ]);
    }

    #[Route('/{id}/acknowledge', name: 'acknowledge', methods: ['POST'])]
    public function acknowledge(int $id, Request $request): JsonResponse
    {
        $alert = $this->alertService->getAlert($id);

        if (!$alert) {
            return $this->json([
                'success' => false,
                'message' => 'Alert not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->alertService->acknowledgeAlert($alert, $data['notes'] ?? null);

        return $this->json([
            'success' => true,
            'message' => 'Alert acknowledged',
            'data' => $this->serializeAlert($alert),
        ]);
    }

    #[Route('/{id}/resolve', name: 'resolve', methods: ['POST'])]
    public function resolve(int $id, Request $request): JsonResponse
    {
        $alert = $this->alertService->getAlert($id);

        if (!$alert) {
            return $this->json([
                'success' => false,
                'message' => 'Alert not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->alertService->resolveAlert($alert, $data['notes'] ?? null);

        return $this->json([
            'success' => true,
            'message' => 'Alert resolved',
            'data' => $this->serializeAlert($alert),
        ]);
    }

    private function serializeAlert(LowStockAlert $alert): array
    {
        return [
            'id' => $alert->getId(),
            'product' => [
                'id' => $alert->getProduct()->getId(),
                'sku' => $alert->getProduct()->getSku(),
                'name' => $alert->getProduct()->getName(),
                'barcode' => $alert->getProduct()->getBarcode(),
            ],
            'currentStock' => $alert->getCurrentStock(),
            'minStockLevel' => $alert->getMinStockLevel(),
            'deficit' => $alert->getDeficit(),
            'severity' => $alert->getSeverity(),
            'status' => $alert->getStatus(),
            'createdAt' => $alert->getCreatedAt()->format('c'),
            'acknowledgedAt' => $alert->getAcknowledgedAt()?->format('c'),
            'resolvedAt' => $alert->getResolvedAt()?->format('c'),
            'notes' => $alert->getNotes(),
        ];
    }
}

