<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GoodsReceipt;
use App\Entity\GoodsReceiptItem;
use App\Entity\Product;
use App\Repository\GoodsReceiptRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class GoodsReceiptService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GoodsReceiptRepository $goodsReceiptRepository,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public function createGoodsReceipt(
        ?string $supplierName = null,
        ?string $purchaseOrderNumber = null,
        ?string $notes = null
    ): GoodsReceipt {
        $goodsReceipt = new GoodsReceipt();
        $goodsReceipt->setSupplierName($supplierName);
        $goodsReceipt->setPurchaseOrderNumber($purchaseOrderNumber);
        $goodsReceipt->setNotes($notes);

        $this->entityManager->persist($goodsReceipt);
        $this->entityManager->flush();

        return $goodsReceipt;
    }

    public function addItem(
        GoodsReceipt $goodsReceipt,
        Product $product,
        int $expectedQuantity
    ): GoodsReceiptItem {
        $item = new GoodsReceiptItem();
        $item->setProduct($product);
        $item->setExpectedQuantity($expectedQuantity);

        $goodsReceipt->addItem($item);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }

    public function addItemBySku(
        GoodsReceipt $goodsReceipt,
        string $sku,
        int $expectedQuantity
    ): ?GoodsReceiptItem {
        $product = $this->productRepository->findBySku($sku);

        if (!$product) {
            return null;
        }

        return $this->addItem($goodsReceipt, $product, $expectedQuantity);
    }

    public function startProcessing(GoodsReceipt $goodsReceipt): GoodsReceipt
    {
        if ($goodsReceipt->getStatus() !== GoodsReceipt::STATUS_PENDING) {
            throw new \InvalidArgumentException('Goods receipt must be in pending status to start processing');
        }

        $goodsReceipt->startProcessing();
        $this->entityManager->flush();

        return $goodsReceipt;
    }

    public function completeReceipt(GoodsReceipt $goodsReceipt): GoodsReceipt
    {
        if ($goodsReceipt->getStatus() !== GoodsReceipt::STATUS_IN_PROGRESS) {
            throw new \InvalidArgumentException('Goods receipt must be in progress to complete');
        }

        $goodsReceipt->complete();
        $this->entityManager->flush();

        return $goodsReceipt;
    }

    public function cancelReceipt(GoodsReceipt $goodsReceipt): GoodsReceipt
    {
        if ($goodsReceipt->getStatus() === GoodsReceipt::STATUS_COMPLETED) {
            throw new \InvalidArgumentException('Cannot cancel a completed goods receipt');
        }

        $goodsReceipt->cancel();
        $this->entityManager->flush();

        return $goodsReceipt;
    }

    public function getReceipt(int $id): ?GoodsReceipt
    {
        return $this->goodsReceiptRepository->find($id);
    }

    public function getReceiptByNumber(string $receiptNumber): ?GoodsReceipt
    {
        return $this->goodsReceiptRepository->findByReceiptNumber($receiptNumber);
    }

    /**
     * @return GoodsReceipt[]
     */
    public function getPendingReceipts(): array
    {
        return $this->goodsReceiptRepository->findPending();
    }

    /**
     * @return GoodsReceipt[]
     */
    public function getInProgressReceipts(): array
    {
        return $this->goodsReceiptRepository->findInProgress();
    }

    /**
     * @return GoodsReceipt[]
     */
    public function getRecentReceipts(int $limit = 10): array
    {
        return $this->goodsReceiptRepository->findRecent($limit);
    }

    /**
     * @return array{total: int, pending: int, inProgress: int, completed: int, cancelled: int}
     */
    public function getReceiptStats(): array
    {
        $all = $this->goodsReceiptRepository->findAll();

        $stats = [
            'total' => count($all),
            'pending' => 0,
            'inProgress' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];

        foreach ($all as $receipt) {
            match ($receipt->getStatus()) {
                GoodsReceipt::STATUS_PENDING => $stats['pending']++,
                GoodsReceipt::STATUS_IN_PROGRESS => $stats['inProgress']++,
                GoodsReceipt::STATUS_COMPLETED => $stats['completed']++,
                GoodsReceipt::STATUS_CANCELLED => $stats['cancelled']++,
                default => null,
            };
        }

        return $stats;
    }
}

