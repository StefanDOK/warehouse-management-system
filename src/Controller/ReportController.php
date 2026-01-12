<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\InventoryReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reports', name: 'reports_')]
class ReportController extends AbstractController
{
    public function __construct(
        private readonly InventoryReportService $inventoryReportService,
    ) {
    }

    #[Route('/daily', name: 'daily', methods: ['GET'])]
    public function daily(Request $request): JsonResponse
    {
        $dateString = $request->query->get('date');
        $date = $dateString ? new \DateTimeImmutable($dateString) : null;

        $report = $this->inventoryReportService->generateDailyReport($date);

        return $this->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    #[Route('/inventory-summary', name: 'inventory_summary', methods: ['GET'])]
    public function inventorySummary(): JsonResponse
    {
        $summary = $this->inventoryReportService->getInventorySummary();

        return $this->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    #[Route('/low-stock', name: 'low_stock', methods: ['GET'])]
    public function lowStock(): JsonResponse
    {
        $products = $this->inventoryReportService->getLowStockProducts();

        return $this->json([
            'success' => true,
            'count' => count($products),
            'data' => $products,
        ]);
    }

    #[Route('/valuation', name: 'valuation', methods: ['GET'])]
    public function valuation(): JsonResponse
    {
        $report = $this->inventoryReportService->generateValuationReport();

        return $this->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    #[Route('/stock-by-location', name: 'stock_by_location', methods: ['GET'])]
    public function stockByLocation(): JsonResponse
    {
        $report = $this->inventoryReportService->getStockByLocationReport();

        return $this->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    #[Route('/movements', name: 'movements_summary', methods: ['GET'])]
    public function movementsSummary(Request $request): JsonResponse
    {
        $dateString = $request->query->get('date');
        $date = $dateString ? new \DateTimeImmutable($dateString) : new \DateTimeImmutable('today');

        $summary = $this->inventoryReportService->getDailyMovementsSummary($date);

        return $this->json([
            'success' => true,
            'date' => $date->format('Y-m-d'),
            'data' => $summary,
        ]);
    }

    #[Route('/top-products', name: 'top_products', methods: ['GET'])]
    public function topProducts(Request $request): JsonResponse
    {
        $dateString = $request->query->get('date');
        $date = $dateString ? new \DateTimeImmutable($dateString) : new \DateTimeImmutable('today');
        $limit = (int)($request->query->get('limit', 10));

        $products = $this->inventoryReportService->getTopMovingProducts($date, $limit);

        return $this->json([
            'success' => true,
            'date' => $date->format('Y-m-d'),
            'data' => $products,
        ]);
    }
}

