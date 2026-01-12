<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductReturnItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductReturnItemRepository::class)]
#[ORM\Table(name: 'product_return_items')]
class ProductReturnItem
{
    public const CONDITION_NEW = 'new';
    public const CONDITION_GOOD = 'good';
    public const CONDITION_DAMAGED = 'damaged';
    public const CONDITION_DEFECTIVE = 'defective';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductReturn::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProductReturn $productReturn = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(type: 'string', length: 20)]
    private string $condition = self::CONDITION_GOOD;

    #[ORM\Column(type: 'boolean')]
    private bool $restockable = true;

    #[ORM\ManyToOne(targetEntity: Shelf::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Shelf $allocatedShelf = null;

    #[ORM\Column(type: 'boolean')]
    private bool $restocked = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductReturn(): ?ProductReturn
    {
        return $this->productReturn;
    }

    public function setProductReturn(?ProductReturn $productReturn): self
    {
        $this->productReturn = $productReturn;
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function setCondition(string $condition): self
    {
        $this->condition = $condition;
        $this->restockable = in_array($condition, [self::CONDITION_NEW, self::CONDITION_GOOD]);
        return $this;
    }

    public function isRestockable(): bool
    {
        return $this->restockable;
    }

    public function setRestockable(bool $restockable): self
    {
        $this->restockable = $restockable;
        return $this;
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

    public function isRestocked(): bool
    {
        return $this->restocked;
    }

    public function setRestocked(bool $restocked): self
    {
        $this->restocked = $restocked;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }
}

