<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StockRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StockRepository::class)]
#[ORM\Table(name: 'stocks')]
#[ORM\UniqueConstraint(name: 'product_shelf_unique', columns: ['product_id', 'shelf_id'])]
#[ORM\HasLifecycleCallbacks]
class Stock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'stocks')]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: Shelf::class, inversedBy: 'stocks')]
    #[ORM\JoinColumn(nullable: false)]
    private Shelf $shelf;

    #[ORM\Column(type: 'integer')]
    #[Assert\PositiveOrZero]
    private int $quantity = 0;

    #[ORM\Column(type: 'integer')]
    private int $reservedQuantity = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getShelf(): Shelf
    {
        return $this->shelf;
    }

    public function setShelf(Shelf $shelf): self
    {
        $this->shelf = $shelf;
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

    public function addQuantity(int $quantity): self
    {
        $this->quantity += $quantity;
        return $this;
    }

    public function removeQuantity(int $quantity): self
    {
        $this->quantity = max(0, $this->quantity - $quantity);
        return $this;
    }

    public function getReservedQuantity(): int
    {
        return $this->reservedQuantity;
    }

    public function setReservedQuantity(int $reservedQuantity): self
    {
        $this->reservedQuantity = $reservedQuantity;
        return $this;
    }

    public function getAvailableQuantity(): int
    {
        return $this->quantity - $this->reservedQuantity;
    }

    public function reserveQuantity(int $quantity): bool
    {
        if ($this->getAvailableQuantity() >= $quantity) {
            $this->reservedQuantity += $quantity;
            return true;
        }
        return false;
    }

    public function releaseReservation(int $quantity): self
    {
        $this->reservedQuantity = max(0, $this->reservedQuantity - $quantity);
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

