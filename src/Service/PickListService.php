<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\PickList;
use App\Entity\PickListItem;
use App\Repository\OrderRepository;
use App\Repository\PickListRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;

class PickListService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PickListRepository $pickListRepository,
        private readonly OrderRepository $orderRepository,
        private readonly StockRepository $stockRepository,
    ) {
    }

    /**
     * Generate pick list for an order
     */
    public function generatePickList(Order $order): PickList
    {
        if ($order->getPickList()) {
            throw new \InvalidArgumentException('Order already has a pick list');
        }

        $pickList = new PickList();
        $pickList->setOrder($order);

        foreach ($order->getItems() as $orderItem) {
            $product = $orderItem->getProduct();
            $requiredQuantity = $orderItem->getQuantity();

            // Find stock locations for this product
            $stocks = $this->stockRepository->findAvailableStockForProduct($product, $requiredQuantity);

            $remainingQuantity = $requiredQuantity;

            foreach ($stocks as $stock) {
                if ($remainingQuantity <= 0) {
                    break;
                }

                $availableQuantity = $stock->getAvailableQuantity();
                if ($availableQuantity <= 0) {
                    continue;
                }

                $pickQuantity = min($remainingQuantity, $availableQuantity);

                $pickListItem = new PickListItem();
                $pickListItem->setProduct($product);
                $pickListItem->setShelf($stock->getShelf());
                $pickListItem->setQuantity($pickQuantity);

                $pickList->addItem($pickListItem);

                // Reserve the stock
                $stock->reserveQuantity($pickQuantity);

                $remainingQuantity -= $pickQuantity;
            }

            if ($remainingQuantity > 0) {
                // Not enough stock - could log warning or throw exception
            }
        }

        $order->setStatus(Order::STATUS_PROCESSING);

        $this->entityManager->persist($pickList);
        $this->entityManager->flush();

        return $pickList;
    }

    /**
     * Generate pick lists for multiple orders (batch processing)
     * @param Order[] $orders
     * @return PickList[]
     */
    public function generatePickListsBatch(array $orders): array
    {
        $pickLists = [];

        foreach ($orders as $order) {
            if (!$order->getPickList() && $order->getStatus() === Order::STATUS_PENDING) {
                $pickLists[] = $this->generatePickList($order);
            }
        }

        return $pickLists;
    }

    /**
     * Get pick list by ID
     */
    public function getPickList(int $id): ?PickList
    {
        return $this->pickListRepository->find($id);
    }

    /**
     * Get pick list by number
     */
    public function getPickListByNumber(string $pickListNumber): ?PickList
    {
        return $this->pickListRepository->findByPickListNumber($pickListNumber);
    }

    /**
     * Start picking process
     */
    public function startPicking(PickList $pickList): PickList
    {
        if ($pickList->getStatus() !== PickList::STATUS_PENDING) {
            throw new \InvalidArgumentException('Pick list must be in pending status to start');
        }

        $pickList->start();
        $pickList->getOrder()?->startPicking();

        $this->entityManager->flush();

        return $pickList;
    }

    /**
     * Complete pick list
     */
    public function completePicking(PickList $pickList): PickList
    {
        if ($pickList->getStatus() !== PickList::STATUS_IN_PROGRESS) {
            throw new \InvalidArgumentException('Pick list must be in progress to complete');
        }

        $pickList->complete();
        $pickList->getOrder()?->completePicking();

        $this->entityManager->flush();

        return $pickList;
    }

    /**
     * Cancel pick list and release reserved stock
     */
    public function cancelPickList(PickList $pickList): PickList
    {
        if ($pickList->getStatus() === PickList::STATUS_COMPLETED) {
            throw new \InvalidArgumentException('Cannot cancel a completed pick list');
        }

        // Release reserved stock
        foreach ($pickList->getItems() as $item) {
            $stock = $this->stockRepository->findByProductAndShelf($item->getProduct(), $item->getShelf());
            if ($stock) {
                $stock->releaseReservation($item->getQuantity() - $item->getPickedQuantity());
            }
        }

        $pickList->cancel();

        $this->entityManager->flush();

        return $pickList;
    }

    /**
     * Get pending pick lists
     * @return PickList[]
     */
    public function getPendingPickLists(): array
    {
        return $this->pickListRepository->findPending();
    }

    /**
     * Get in-progress pick lists
     * @return PickList[]
     */
    public function getInProgressPickLists(): array
    {
        return $this->pickListRepository->findInProgress();
    }

    /**
     * Get pick list items optimized by location (for efficient picking path)
     */
    public function getOptimizedPickingPath(PickList $pickList): array
    {
        $items = $pickList->getItems()->toArray();

        // Sort by aisle, rack, level for optimal picking path
        usort($items, function (PickListItem $a, PickListItem $b) {
            $shelfA = $a->getShelf();
            $shelfB = $b->getShelf();

            $aisleCompare = strcmp($shelfA->getAisle(), $shelfB->getAisle());
            if ($aisleCompare !== 0) {
                return $aisleCompare;
            }

            $rackCompare = strcmp($shelfA->getRack(), $shelfB->getRack());
            if ($rackCompare !== 0) {
                return $rackCompare;
            }

            return strcmp($shelfA->getLevel(), $shelfB->getLevel());
        });

        return $items;
    }
}

