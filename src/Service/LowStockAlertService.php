<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LowStockAlert;
use App\Entity\Product;
use App\Repository\LowStockAlertRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class LowStockAlertService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LowStockAlertRepository $alertRepository,
        private readonly ProductRepository $productRepository,
    ) {
    }

    /**
     * Check all products and create alerts for low stock
     */
    public function checkAllProducts(): array
    {
        $products = $this->productRepository->findAll();
        $newAlerts = [];
        $resolvedAlerts = [];

        foreach ($products as $product) {
            if ($product->isLowStock()) {
                // Check if there's already an active alert
                if (!$this->alertRepository->hasActiveAlert($product)) {
                    $alert = $this->createAlert($product);
                    $newAlerts[] = $alert;
                }
            } else {
                // Resolve any active alerts for this product
                $activeAlerts = $this->alertRepository->findActiveByProduct($product);
                foreach ($activeAlerts as $alert) {
                    $alert->resolve();
                    $resolvedAlerts[] = $alert;
                }
            }
        }

        $this->entityManager->flush();

        return [
            'checked' => count($products),
            'newAlerts' => count($newAlerts),
            'resolvedAlerts' => count($resolvedAlerts),
            'alerts' => array_map(fn($a) => $this->serializeAlert($a), $newAlerts),
        ];
    }

    /**
     * Check a specific product and create/resolve alerts
     */
    public function checkProduct(Product $product): ?LowStockAlert
    {
        if ($product->isLowStock()) {
            if (!$this->alertRepository->hasActiveAlert($product)) {
                return $this->createAlert($product);
            }
        } else {
            // Resolve active alerts
            $activeAlerts = $this->alertRepository->findActiveByProduct($product);
            foreach ($activeAlerts as $alert) {
                $alert->resolve();
            }
            $this->entityManager->flush();
        }

        return null;
    }

    /**
     * Create a new low stock alert
     */
    public function createAlert(Product $product): LowStockAlert
    {
        $currentStock = $product->getTotalStock();
        $minStock = $product->getMinStockLevel();

        $alert = new LowStockAlert();
        $alert->setProduct($product);
        $alert->setCurrentStock($currentStock);
        $alert->setMinStockLevel($minStock);
        $alert->setSeverity(LowStockAlert::calculateSeverity($currentStock, $minStock));

        $this->entityManager->persist($alert);
        $this->entityManager->flush();

        return $alert;
    }

    /**
     * Acknowledge an alert
     */
    public function acknowledgeAlert(LowStockAlert $alert, ?string $notes = null): LowStockAlert
    {
        $alert->acknowledge();
        if ($notes) {
            $alert->setNotes($notes);
        }
        $this->entityManager->flush();

        return $alert;
    }

    /**
     * Resolve an alert
     */
    public function resolveAlert(LowStockAlert $alert, ?string $notes = null): LowStockAlert
    {
        $alert->resolve();
        if ($notes) {
            $alert->setNotes(($alert->getNotes() ?? '') . "\nResolution: " . $notes);
        }
        $this->entityManager->flush();

        return $alert;
    }

    /**
     * Get active alerts
     * @return LowStockAlert[]
     */
    public function getActiveAlerts(): array
    {
        return $this->alertRepository->findActive();
    }

    /**
     * Get alerts by severity
     * @return LowStockAlert[]
     */
    public function getAlertsBySeverity(string $severity): array
    {
        return $this->alertRepository->findBySeverity($severity);
    }

    /**
     * Get alert by ID
     */
    public function getAlert(int $id): ?LowStockAlert
    {
        return $this->alertRepository->find($id);
    }

    /**
     * Get active alerts count
     */
    public function getActiveAlertsCount(): int
    {
        return $this->alertRepository->countActive();
    }

    /**
     * Get alerts summary
     */
    public function getAlertsSummary(): array
    {
        $active = $this->alertRepository->findActive();

        $bySeverity = [
            LowStockAlert::SEVERITY_CRITICAL => 0,
            LowStockAlert::SEVERITY_HIGH => 0,
            LowStockAlert::SEVERITY_MEDIUM => 0,
            LowStockAlert::SEVERITY_LOW => 0,
        ];

        foreach ($active as $alert) {
            $bySeverity[$alert->getSeverity()]++;
        }

        return [
            'totalActive' => count($active),
            'bySeverity' => $bySeverity,
        ];
    }

    private function serializeAlert(LowStockAlert $alert): array
    {
        return [
            'id' => $alert->getId(),
            'product' => [
                'id' => $alert->getProduct()->getId(),
                'sku' => $alert->getProduct()->getSku(),
                'name' => $alert->getProduct()->getName(),
            ],
            'currentStock' => $alert->getCurrentStock(),
            'minStockLevel' => $alert->getMinStockLevel(),
            'deficit' => $alert->getDeficit(),
            'severity' => $alert->getSeverity(),
            'status' => $alert->getStatus(),
            'createdAt' => $alert->getCreatedAt()->format('c'),
        ];
    }
}

