<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GoodsReceiptItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GoodsReceiptItemRepository::class)]
#[ORM\Table(name: 'goods_receipt_items')]
class GoodsReceiptItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: GoodsReceipt::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GoodsReceipt $goodsReceipt = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $expectedQuantity = 0;

    #[ORM\Column(type: 'integer')]
    #[Assert\PositiveOrZero]
    private int $receivedQuantity = 0;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $scannedBarcode = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $scannedAt = null;

    #[ORM\ManyToOne(targetEntity: Shelf::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Shelf $allocatedShelf = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGoodsReceipt(): ?GoodsReceipt
    {
        return $this->goodsReceipt;
    }

    public function setGoodsReceipt(?GoodsReceipt $goodsReceipt): self
    {
        $this->goodsReceipt = $goodsReceipt;
        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getExpectedQuantity(): int
    {
        return $this->expectedQuantity;
    }

    public function setExpectedQuantity(int $expectedQuantity): self
    {
        $this->expectedQuantity = $expectedQuantity;
        return $this;
    }

    public function getReceivedQuantity(): int
    {
        return $this->receivedQuantity;
    }

    public function setReceivedQuantity(int $receivedQuantity): self
    {
        $this->receivedQuantity = $receivedQuantity;
        return $this;
    }

    public function getScannedBarcode(): ?string
    {
        return $this->scannedBarcode;
    }

    public function setScannedBarcode(?string $scannedBarcode): self
    {
        $this->scannedBarcode = $scannedBarcode;
        $this->scannedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getScannedAt(): ?\DateTimeImmutable
    {
        return $this->scannedAt;
    }

    public function getAllocatedShelf(): ?Shelf
    {
        return $this->allocatedShelf;
    }

    public function setAllocatedShelf(?Shelf $allocatedShelf): self
    {
        $this->allocatedShelf = $allocatedShelf;
        return $this;
    }

    public function isFullyReceived(): bool
    {
        return $this->receivedQuantity >= $this->expectedQuantity;
    }

    public function getDiscrepancy(): int
    {
        return $this->receivedQuantity - $this->expectedQuantity;
    }
}

